<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoPrestamoAcceso;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Préstamo electrónico (diseño de expedientes §3.10): mientras dure, el solicitante consulta los
 * documentos del expediente (o solo los de la versión pública) y cada consulta o descarga queda
 * registrada. Los clasificados y reservados no se descargan.
 */
class PrestamoConsultaService
{
    /** Formatos que el navegador muestra sin descargarlos. */
    private const EXTENSIONES_VISIBLES = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'];

    /**
     * Documentos que el solicitante puede consultar, con lo que se puede hacer con cada uno.
     */
    public function consulta(OfiArchivoPrestamo $prestamo, User $usuario): array
    {
        $this->autorizar($prestamo, $usuario);
        $descargable = ! $this->esReservado($prestamo);

        return [
            'prestamo_id' => $prestamo->id,
            'expediente' => $prestamo->expediente->only(['id', 'numero_expediente', 'nombre_expediente', 'dependencia_nom', 'nivel_acceso']),
            'hasta' => $prestamo->fecha_devolucion_esperada,
            'version_publica' => $prestamo->version_publica,
            'documentos' => $this->documentos($prestamo)->map(function (OfiArchivoExpedienteDocumento $doc) use ($descargable) {
                $archivo = $this->archivo($doc);
                $extension = $archivo ? strtolower(pathinfo($archivo['path'], PATHINFO_EXTENSION)) : null;

                return [
                    'id' => $doc->id,
                    'numero_folio' => $doc->numero_folio,
                    'tipo_documental' => $doc->tipo_documental,
                    'asunto' => $doc->documentable?->asunto ?? $doc->asunto ?? $doc->detalle,
                    'fecha_documento' => $doc->fecha_documento,
                    'nombre' => $archivo['nombre'] ?? null,
                    'disponible' => (bool) $archivo,
                    'visible' => $archivo && in_array($extension, self::EXTENSIONES_VISIBLES, true),
                    'descargable' => $archivo && $descargable,
                ];
            })->values(),
        ];
    }

    /**
     * Binario de un documento del préstamo; deja registrado el acceso.
     *
     * @return array{disk: string, path: string, nombre: string}
     */
    public function abrir(OfiArchivoPrestamo $prestamo, int $documentoId, string $accion, User $usuario, Request $request): array
    {
        $this->autorizar($prestamo, $usuario);

        $doc = $this->documentos($prestamo)->firstWhere('id', $documentoId);
        $archivo = $doc ? $this->archivo($doc) : null;
        if (! $archivo || ! Storage::disk($archivo['disk'])->exists($archivo['path'])) {
            throw ValidationException::withMessages(['documento' => 'El documento no tiene archivo digital para consultar']);
        }

        $extension = strtolower(pathinfo($archivo['path'], PATHINFO_EXTENSION));
        if ($accion === 'ver' && ! in_array($extension, self::EXTENSIONES_VISIBLES, true)) {
            throw ValidationException::withMessages(['documento' => 'Este formato no se puede ver en el navegador']);
        }
        if ($accion === 'descargar' && $this->esReservado($prestamo)) {
            throw new AuthorizationException("El expediente es {$prestamo->expediente->nivel_acceso}: sus documentos solo se consultan, no se descargan");
        }

        OfiArchivoPrestamoAcceso::create([
            'prestamo_id' => $prestamo->id,
            'documento_id' => $doc->id,
            'user_id' => $usuario->id,
            'accion' => $accion,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        return $archivo;
    }

    /**
     * Solo el solicitante, en un préstamo electrónico vigente.
     */
    private function autorizar(OfiArchivoPrestamo $prestamo, User $usuario): void
    {
        if ((int) $prestamo->solicitante_id !== (int) $usuario->id) {
            throw new AuthorizationException('Solo quien solicitó el préstamo consulta sus documentos');
        }
        if ($prestamo->modalidad !== 'electronico') {
            throw new AuthorizationException('El préstamo no es electrónico: el expediente se consulta en el archivo');
        }
        if ($prestamo->estado !== 'prestado' || ! $prestamo->fecha_devolucion_esperada?->isFuture()) {
            throw new AuthorizationException('El acceso electrónico no está vigente');
        }
    }

    /** Documentos activos del expediente, o los de la versión pública. */
    private function documentos(OfiArchivoPrestamo $prestamo): Collection
    {
        return $prestamo->expediente->documentos()
            ->where('activo', true)
            ->when($prestamo->documentos_autorizados !== null, fn ($q) => $q->whereIn('id', $prestamo->documentos_autorizados))
            ->with('documentable')
            ->orderBy('numero_folio')
            ->get();
    }

    /** Dónde está el binario: el subido al expediente o el del radicado incorporado. */
    private function archivo(OfiArchivoExpedienteDocumento $doc): ?array
    {
        return ArchivoFolio::ubicacion($doc);
    }

    private function esReservado(OfiArchivoPrestamo $prestamo): bool
    {
        return in_array($prestamo->expediente->nivel_acceso, ['clasificado', 'reservado'], true);
    }
}
