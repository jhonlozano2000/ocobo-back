<?php

namespace App\Services\OfiArchivo;

use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoIntegridadIncidente;
use App\Models\OfiArchivo\OfiArchivoIntegridadRevision;
use App\Models\OfiArchivo\OfiArchivoIntegridadVerificacion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * B21 (diseño de expedientes §8): recalcula la huella SHA-256 de los folios y la compara con la
 * registrada. Cada semana se revisa una muestra (primero los folios nunca verificados y luego los
 * de revisión más antigua) y, antes de transferir, todos los folios del lote.
 *
 * Resultados: íntegro; alterado (la huella no coincide); faltante (tiene huella o ruta pero el
 * archivo no está); sin huella (hay archivo pero no huella con qué compararlo); sin archivo
 * (radicado en soporte físico: nada que verificar). Alterado, faltante y sin huella abren un
 * incidente, uno por folio mientras siga abierto, que queda en el historial del expediente.
 */
class IntegridadArchivoService
{
    public const PERMISO_VER = 'Gestion de Archivo -> Integridad -> Ver';

    public const PERMISO_RESOLVER = 'Gestion de Archivo -> Integridad -> Resolver';

    public const TIPO_NOTIFICACION = 'integridad_archivo';

    public const EVENTO_INCIDENTE = 'incidente_integridad';

    public const EVENTO_RESUELTO = 'incidente_integridad_resuelto';

    public const ETIQUETAS = [
        OfiArchivoIntegridadRevision::INTEGRO => 'íntegro',
        OfiArchivoIntegridadRevision::ALTERADO => 'alterado',
        OfiArchivoIntegridadRevision::FALTANTE => 'archivo faltante',
        OfiArchivoIntegridadRevision::SIN_HUELLA => 'sin huella',
        OfiArchivoIntegridadRevision::SIN_ARCHIVO => 'sin archivo digital',
    ];

    private const POR_LOTE = 200;

    public function __construct(private ExpedienteEventosService $eventos) {}

    /** Muestra semanal: el porcentaje de los folios activos de expedientes no eliminados. */
    public function muestra(?int $porcentaje = null, ?User $usuario = null): OfiArchivoIntegridadVerificacion
    {
        $porcentaje = min(100, max(1, $porcentaje ?? ConfiguracionArchivo::integridadPorcentajeMuestra()));
        $base = OfiArchivoExpedienteDocumento::query()
            ->where('ofi_archivo_expedientes_documentos.activo', true)
            ->whereHas('expediente', fn ($q) => $q->where('estado', '!=', 'eliminado'));
        $cantidad = (int) ceil((clone $base)->count() * $porcentaje / 100);

        $ultimas = OfiArchivoIntegridadRevision::selectRaw('documento_id, MAX(created_at) AS ultima')->groupBy('documento_id');
        $ids = $cantidad === 0 ? [] : (clone $base)
            ->leftJoinSub($ultimas, 'r', 'r.documento_id', '=', 'ofi_archivo_expedientes_documentos.id')
            ->orderByRaw('r.ultima IS NOT NULL')
            ->orderBy('r.ultima')
            ->orderBy('ofi_archivo_expedientes_documentos.id')
            ->limit($cantidad)
            ->pluck('ofi_archivo_expedientes_documentos.id')
            ->all();

        return $this->ejecutar($ids, ['alcance' => 'muestra', 'porcentaje' => $porcentaje, 'user_id' => $usuario?->id]);
    }

    /** Todos los folios activos de un expediente. */
    public function verificarExpediente(OfiArchivoExpediente $expediente, ?User $usuario = null): OfiArchivoIntegridadVerificacion
    {
        $ids = $expediente->documentos()->where('activo', true)->orderBy('id')->pluck('id')->all();

        return $this->ejecutar($ids, ['alcance' => 'expediente', 'expediente_id' => $expediente->id, 'user_id' => $usuario?->id]);
    }

    /**
     * Antes de enviar un lote: verifica todos los folios de sus expedientes y devuelve, por
     * expediente, el motivo de los que no pasan.
     *
     * @param  list<int>  $expedienteIds
     * @return array<int, string>
     */
    public function verificarParaTransferencia(array $expedienteIds, User $usuario): array
    {
        $ids = OfiArchivoExpedienteDocumento::whereIn('expediente_id', $expedienteIds)
            ->where('activo', true)->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $verificacion = $this->ejecutar($ids, ['alcance' => 'transferencia', 'user_id' => $usuario->id]);

        return OfiArchivoIntegridadRevision::where('verificacion_id', $verificacion->id)
            ->whereIn('resultado', OfiArchivoIntegridadRevision::PROBLEMAS)
            ->join('ofi_archivo_expedientes_documentos as d', 'd.id', '=', 'ofi_archivo_integridad_revisiones.documento_id')
            ->orderBy('d.numero_folio')
            ->get(['ofi_archivo_integridad_revisiones.expediente_id', 'resultado', 'd.numero_folio'])
            ->groupBy('expediente_id')
            ->map(fn (Collection $filas) => 'La verificación de integridad falló en '
                .$filas->map(fn ($f) => "el folio {$f->numero_folio} (".self::ETIQUETAS[$f->resultado].')')->join(', ', ' y '))
            ->all();
    }

    /**
     * Cierra el incidente con la justificación y el resultado de volver a verificar el folio.
     *
     * @throws ValidationException
     */
    public function resolver(OfiArchivoIntegridadIncidente $incidente, string $resolucion, User $usuario): OfiArchivoIntegridadIncidente
    {
        return DB::transaction(function () use ($incidente, $resolucion, $usuario) {
            $incidente = OfiArchivoIntegridadIncidente::whereKey($incidente->id)->lockForUpdate()->firstOrFail();
            if ($incidente->estado !== 'abierto') {
                throw ValidationException::withMessages(['incidente' => 'El incidente ya fue resuelto']);
            }

            $doc = OfiArchivoExpedienteDocumento::with('documentable')->findOrFail($incidente->documento_id);
            [$resultado] = $this->resultado($doc);

            $incidente->update([
                'estado' => 'resuelto',
                'resuelto_at' => now(),
                'resuelto_por_id' => $usuario->id,
                'resolucion' => $resolucion,
                'resultado_al_resolver' => $resultado,
            ]);
            $this->eventos->registrar($incidente->expediente_id, self::EVENTO_RESUELTO, [
                'incidente_id' => $incidente->id,
                'documento_id' => $doc->id,
                'numero_folio' => $doc->numero_folio,
                'resultado' => $resultado,
            ]);

            return $incidente;
        });
    }

    /**
     * @param  list<int>  $ids
     * @param  array{alcance: string, porcentaje?: int, expediente_id?: int, user_id?: ?int}  $datos
     */
    private function ejecutar(array $ids, array $datos): OfiArchivoIntegridadVerificacion
    {
        $verificacion = OfiArchivoIntegridadVerificacion::create($datos + ['iniciada_at' => now()]);
        $revisados = 0;
        $problemas = 0;
        $nuevos = collect();

        foreach (array_chunk($ids, self::POR_LOTE) as $lote) {
            $documentos = OfiArchivoExpedienteDocumento::with(['documentable', 'expediente:id,numero_expediente,usuario_apertura_id'])
                ->whereIn('id', $lote)->orderBy('id')->get();

            foreach ($documentos as $doc) {
                [$resultado, $esperado, $calculado] = $this->resultado($doc);
                $revision = OfiArchivoIntegridadRevision::create([
                    'verificacion_id' => $verificacion->id,
                    'documento_id' => $doc->id,
                    'expediente_id' => $doc->expediente_id,
                    'resultado' => $resultado,
                    'hash_esperado' => $esperado,
                    'hash_calculado' => $calculado,
                ]);
                $revisados++;

                if (in_array($resultado, OfiArchivoIntegridadRevision::PROBLEMAS, true)) {
                    $problemas++;
                    if ($incidente = $this->abrirIncidente($doc, $revision)) {
                        $nuevos->push($incidente->setRelation('documento', $doc));
                    }
                }
            }
        }

        $verificacion->update(['revisados' => $revisados, 'incidentes' => $problemas, 'terminada_at' => now()]);
        $this->notificar($verificacion, $nuevos);

        return $verificacion;
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} resultado, huella esperada y calculada
     */
    private function resultado(OfiArchivoExpedienteDocumento $doc): array
    {
        $esperado = ArchivoFolio::hashEsperado($doc);
        $ubicacion = ArchivoFolio::ubicacion($doc);
        if (! $ubicacion) {
            return [$esperado ? OfiArchivoIntegridadRevision::FALTANTE : OfiArchivoIntegridadRevision::SIN_ARCHIVO, $esperado, null];
        }

        $calculado = ArchivoFolio::hashCalculado($ubicacion);
        $resultado = match (true) {
            $calculado === null => OfiArchivoIntegridadRevision::FALTANTE,
            $esperado === null => OfiArchivoIntegridadRevision::SIN_HUELLA,
            hash_equals(strtolower($esperado), $calculado) => OfiArchivoIntegridadRevision::INTEGRO,
            default => OfiArchivoIntegridadRevision::ALTERADO,
        };

        return [$resultado, $esperado, $calculado];
    }

    /** Un folio no tiene dos incidentes abiertos: si ya hay uno, no se abre otro. */
    private function abrirIncidente(OfiArchivoExpedienteDocumento $doc, OfiArchivoIntegridadRevision $revision): ?OfiArchivoIntegridadIncidente
    {
        $incidente = DB::transaction(function () use ($doc, $revision) {
            $abierto = OfiArchivoIntegridadIncidente::where('documento_id', $doc->id)
                ->where('estado', 'abierto')->lockForUpdate()->exists();
            if ($abierto) {
                return null;
            }

            return OfiArchivoIntegridadIncidente::create([
                'documento_id' => $doc->id,
                'expediente_id' => $doc->expediente_id,
                'revision_id' => $revision->id,
                'tipo' => $revision->resultado,
                'estado' => 'abierto',
                'detectado_at' => now(),
            ]);
        });

        if ($incidente) {
            $this->eventos->registrar($doc->expediente_id, self::EVENTO_INCIDENTE, [
                'incidente_id' => $incidente->id,
                'documento_id' => $doc->id,
                'numero_folio' => $doc->numero_folio,
                'tipo' => $incidente->tipo,
            ]);
        }

        return $incidente;
    }

    /**
     * Un aviso por corrida con los incidentes nuevos: a quien tiene el permiso de integridad,
     * todos; al responsable de cada expediente, los suyos.
     *
     * @param  Collection<int, OfiArchivoIntegridadIncidente>  $incidentes
     */
    private function notificar(OfiArchivoIntegridadVerificacion $verificacion, Collection $incidentes): void
    {
        if ($incidentes->isEmpty()) {
            return;
        }

        $destinatarios = [];
        foreach ($this->usuariosConPermiso() as $userId) {
            $destinatarios[$userId] = $incidentes;
        }
        foreach ($incidentes->groupBy(fn ($i) => $i->documento->expediente?->usuario_apertura_id) as $userId => $suyos) {
            if ($userId && ! isset($destinatarios[$userId])) {
                $destinatarios[$userId] = $suyos;
            }
        }

        foreach ($destinatarios as $userId => $suyos) {
            $detalle = $suyos->take(5)->map(fn ($i) => $i->documento->expediente?->numero_expediente
                ." folio {$i->documento->numero_folio} (".self::ETIQUETAS[$i->tipo].')')->join('; ');
            $resto = $suyos->count() > 5 ? ' y '.($suyos->count() - 5).' más' : '';

            Notificacion::create([
                'user_id' => $userId,
                'type' => self::TIPO_NOTIFICACION,
                'title' => 'Incidentes de integridad en expedientes',
                'message' => "La verificación de huellas encontró {$suyos->count()} folio(s) con problemas: {$detalle}{$resto}. "
                    .'Revíselos en Gestión de Archivo → Integridad.',
                'data' => [
                    'verificacion_id' => $verificacion->id,
                    'tipo_entidad' => 'integridad',
                    'incidente_ids' => $suyos->pluck('id')->values()->all(),
                    'expediente_ids' => $suyos->pluck('expediente_id')->unique()->values()->all(),
                ],
            ]);
        }
    }

    /** @return list<int> */
    private function usuariosConPermiso(): array
    {
        try {
            return User::permission(self::PERMISO_VER)->pluck('id')->all();
        } catch (PermissionDoesNotExist) {
            return [];
        }
    }
}
