<?php

namespace App\Services\Workflows;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkFlowArchivo;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * M19-30: al completarse una instancia con origen, sus archivos (los de la instancia y las
 * evidencias de sus tareas) se incorporan como folios al expediente abierto del radicado de
 * origen (Acuerdo AGN 001/2024: el expediente reúne todos los documentos del trámite).
 *
 * Se copian al disco del archivo para que el expediente no dependa del ciclo de vida del flujo.
 * El resultado queda en datos_contexto.expediente para que el monitor avise si faltó el expediente.
 */
class ArchivoFlujoEnExpedienteService
{
    public const DISK = 'archivo_expedientes';

    public function __construct(private readonly WorkflowAuditService $auditService) {}

    public function archivar(WorkflowInstancia $instancia): ?array
    {
        if (! $instancia->radicado_id || $instancia->estado !== 'completada') {
            return null;
        }
        // Idempotente: una instancia ya archivada no vuelve a foliar sus archivos
        if (($instancia->datos_contexto['expediente']['estado'] ?? null) === 'incorporado') {
            return $instancia->datos_contexto['expediente'];
        }

        $archivos = $this->archivosDelFlujo($instancia);
        $expediente = $this->expedienteAbierto($instancia->radicado_id);

        if (! $expediente) {
            return $this->registrar($instancia, [
                'estado' => 'sin_expediente',
                'archivos' => $archivos->count(),
                'mensaje' => 'El radicado de origen no está en un expediente abierto; incorpore los archivos del flujo manualmente.',
            ], 'instancia.archivo_pendiente');
        }

        $copiados = [];
        try {
            $documentos = DB::transaction(function () use ($instancia, $expediente, $archivos, &$copiados) {
                $documentos = [];
                foreach ($archivos as $archivo) {
                    $destino = $this->copiar($instancia, $archivo);
                    $copiados[] = $destino['path'];
                    $documentos[] = $this->foliar($instancia, $expediente, $archivo, $destino);
                }

                return $documentos;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($copiados);
            Log::error('M19-30: no se pudieron archivar los documentos del flujo', [
                'instancia_id' => $instancia->id,
                'expediente_id' => $expediente->id,
                'error' => $e->getMessage(),
            ]);

            return $this->registrar($instancia, [
                'estado' => 'error',
                'expediente_id' => $expediente->id,
                'numero_expediente' => $expediente->numero_expediente,
                'archivos' => $archivos->count(),
                'mensaje' => 'No se pudieron incorporar los archivos del flujo al expediente; intente incorporarlos manualmente.',
            ], 'instancia.archivo_fallido');
        }

        return $this->registrar($instancia, [
            'estado' => 'incorporado',
            'expediente_id' => $expediente->id,
            'numero_expediente' => $expediente->numero_expediente,
            'archivos' => count($documentos),
            'folios' => array_map(fn ($d) => $d->numero_folio, $documentos),
        ], 'instancia.archivada');
    }

    /**
     * @return Collection<int, WorkFlowArchivo>
     */
    private function archivosDelFlujo(WorkflowInstancia $instancia): Collection
    {
        $tareas = Tarea::where('instancia_id', $instancia->id)->pluck('id');

        return WorkFlowArchivo::where(function ($q) use ($instancia) {
            $q->where('archivable_type', WorkflowInstancia::class)->where('archivable_id', $instancia->id);
        })->orWhere(function ($q) use ($tareas) {
            $q->where('archivable_type', Tarea::class)->whereIn('archivable_id', $tareas);
        })->orderBy('created_at')->orderBy('id')->get();
    }

    /** Expediente abierto donde está incorporado (y activo) el radicado; el más reciente si hay varios. */
    private function expedienteAbierto(int $radicadoId): ?OfiArchivoExpediente
    {
        return OfiArchivoExpediente::where('estado', 'abierto')
            ->whereIn('id', OfiArchivoExpedienteDocumento::where('documentable_type', VentanillaRadicaReci::class)
                ->where('documentable_id', $radicadoId)
                ->where('activo', true)
                ->select('expediente_id'))
            ->latest('id')
            ->first();
    }

    /**
     * @return array{path: string, hash: string}
     */
    private function copiar(WorkflowInstancia $instancia, WorkFlowArchivo $archivo): array
    {
        $origen = Storage::disk($archivo->disk ?: 'workflows_archivos');
        $stream = $origen->readStream($archivo->ruta_almacenada);
        if (! $stream) {
            throw new \RuntimeException("El archivo {$archivo->id} no existe en el disco del flujo");
        }

        $extension = pathinfo($archivo->nombre_original ?? '', PATHINFO_EXTENSION);
        $path = 'workflows/'.$instancia->id.'/'.Str::uuid().($extension ? '.'.$extension : '');
        try {
            if (! Storage::disk(self::DISK)->writeStream($path, $stream)) {
                throw new \RuntimeException("No se pudo copiar el archivo {$archivo->id} al expediente");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // Integridad: la copia debe coincidir con la huella registrada al subirla al flujo
        $hash = hash_file('sha256', Storage::disk(self::DISK)->path($path));
        if ($archivo->hash_sha256 && ! hash_equals($archivo->hash_sha256, $hash)) {
            Storage::disk(self::DISK)->delete($path);
            throw new \RuntimeException("La huella del archivo {$archivo->id} no coincide con la registrada");
        }

        return ['path' => $path, 'hash' => $hash];
    }

    private function foliar(WorkflowInstancia $instancia, OfiArchivoExpediente $expediente, WorkFlowArchivo $archivo, array $destino): OfiArchivoExpedienteDocumento
    {
        $instancia->loadMissing('workflow:id,nombre');
        $tarea = $archivo->archivable_type === Tarea::class ? Tarea::find($archivo->archivable_id) : null;
        $tipoDocumental = $tarea ? 'Evidencia de flujo' : 'Documento de flujo';

        $detalle = array_filter([
            'Workflow: '.($instancia->workflow?->nombre ?? $instancia->workflow_id),
            'Instancia #'.$instancia->id,
            $tarea ? 'Tarea: '.$tarea->nombre : null,
            'Archivo: '.$archivo->nombre_original,
            'Hash: '.$destino['hash'],
        ]);

        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $expediente->id,
            'tipo' => 'tipo_documental',
            'tipo_documental' => $tipoDocumental,
            'detalle' => implode(' | ', $detalle),
            'usuario_id' => $instancia->usuario_ejecuta_id,
            'archivo_path' => $destino['path'],
            'hash_sha256' => $destino['hash'],
            'nombre_original' => $archivo->nombre_original,
            'formato_archivo' => Str::limit(pathinfo($archivo->nombre_original ?? '', PATHINFO_EXTENSION), 20, ''),
            'tamano_bytes' => $archivo->peso_bytes,
            'fecha_documento' => ($archivo->created_at ?? now())->toDateString(),
            'asunto' => Str::limit($tipoDocumental.': '.$archivo->nombre_original, 500, ''),
            'activo' => true,
            'orden' => 0,
        ]);
    }

    private function registrar(WorkflowInstancia $instancia, array $resultado, string $accion): array
    {
        $resultado['fecha'] = now()->toIso8601String();

        $contexto = $instancia->datos_contexto ?? [];
        $contexto['expediente'] = $resultado;
        $instancia->update(['datos_contexto' => $contexto]);

        $this->auditService->registrar($accion, $instancia->workflow_id, $instancia->id, $resultado);

        return $resultado;
    }
}
