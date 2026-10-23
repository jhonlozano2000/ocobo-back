<?php

namespace App\Services\MiBandeja;

use App\Helpers\ArchivoHelper;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\User;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;

/**
 * Radicación de un grupo colaborativo de un documento de Mis Documentos (editor en línea). Cuando todos
 * cumplieron y firmaron aparece en Pendientes por radicar de Ventanilla (enviado), igual que un grupo con plantilla,
 * o el creador o un firmante lo radica como interno. En ambos casos el archivo digital es el PDF del editor con la
 * hoja de constancia de firmas.
 */
class RadicacionGrupoDocumentoService
{
    public function __construct(private DocumentoExportService $exportador) {}

    /**
     * Motivo por el que el documento del grupo aún no se puede radicar, o null si está listo.
     */
    public function pendiente(MiBandejaTemp $grupo): ?string
    {
        if (! $grupo->documento_id) {
            return 'El grupo no es de un documento del editor.';
        }

        if ($grupo->estado_grupo !== 'activo' || $grupo->estado === 'finalizado') {
            return 'El grupo ya no está activo.';
        }

        if ($grupo->firmantes()->count() === 0 || ! $grupo->todosTerminados()) {
            return 'Todos los miembros deben cumplir su tarea y los firmantes firmar antes de radicar.';
        }

        return null;
    }

    /**
     * Motivo por el que el usuario no puede radicar el grupo como interno, o null si puede.
     */
    public function impedimento(MiBandejaTemp $grupo, User $user): ?string
    {
        if ($motivo = $this->pendiente($grupo)) {
            return $motivo;
        }

        $esFirmante = $grupo->firmantes()->where('user_id', $user->id)->exists();
        if ($grupo->usua_crea_id !== $user->id && ! $esFirmante) {
            return 'Solo el creador del grupo o un firmante pueden radicar el documento.';
        }

        return null;
    }

    /**
     * Guarda el PDF firmado del documento en el disco del radicado.
     *
     * @return array{archivo: string, nom_origi: string, hash_sha256: string, archivo_tipo: string, archivo_peso: int}
     */
    public function guardarPdf(MiBandejaTemp $grupo, string $disco, string $numRadicado): array
    {
        $documento = $grupo->documento;
        $temporal = $this->exportador->exportarPdf($documento, true);

        try {
            $contenido = file_get_contents($temporal);
        } finally {
            @unlink($temporal);
        }

        $nombre = $numRadicado.'_'.time().'.pdf';
        ArchivoHelper::guardarContenido($nombre, $contenido, $disco);

        return [
            'archivo' => $nombre,
            'nom_origi' => ($documento->titulo ?: 'Documento').'.pdf',
            'hash_sha256' => hash('sha256', $contenido),
            'archivo_tipo' => 'application/pdf',
            'archivo_peso' => strlen($contenido),
        ];
    }

    /** El grupo queda cerrado: su documento ya es un radicado. */
    public function finalizar(MiBandejaTemp $grupo): void
    {
        $grupo->update([
            'estado' => 'finalizado',
            'estado_grupo' => 'inactivo',
            'respuesta_final' => 'radicado',
        ]);
    }
}
