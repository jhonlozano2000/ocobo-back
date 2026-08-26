<?php

namespace App\Services\Workflows;

use App\Helpers\ArchivoHelper;
use App\Models\Workflows\WorkFlowArchivo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkFlowArchivoService
{
    private const DISK = 'workflows_archivos';

    public function __construct(
        private readonly WorkflowAuditService $auditService
    ) {}

    public function listar(int $workflowId, ?string $archivableType = null, ?int $archivableId = null): Collection
    {
        $query = WorkFlowArchivo::where('workflow_id', $workflowId)
            ->with('uploader:id,nombres,apellidos');

        if ($archivableType && $archivableId) {
            $query->where('archivable_type', $archivableType)
                ->where('archivable_id', $archivableId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function subir(
        UploadedFile $archivo,
        int $workflowId,
        string $archivableType,
        int $archivableId,
        string $categoria = 'adjunto'
    ): WorkFlowArchivo {
        return DB::transaction(function () use ($archivo, $workflowId, $archivableType, $archivableId, $categoria) {
            $archivoSeguro = ArchivoHelper::validarArchivoSeguro($archivo);

            if (! ($archivoSeguro['valido'] ?? false)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => [$archivoSeguro['error'] ?? 'Archivo no permitido'],
                ]);
            }

            $metadatos = ArchivoHelper::guardarUploadedConHash($archivo, self::DISK);

            if (! $metadatos) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => ['No se pudo almacenar el archivo'],
                ]);
            }

            $almacenado = WorkFlowArchivo::create([
                'workflow_id' => $workflowId,
                'archivable_type' => $archivableType,
                'archivable_id' => $archivableId,
                'nombre_original' => $archivo->getClientOriginalName(),
                'nombre_almacenado' => $metadatos['nombre_almacenado'],
                'ruta_almacenada' => $metadatos['ruta'],
                'mime_type' => $archivo->getMimeType(),
                'peso_bytes' => $archivo->getSize(),
                'hash_sha256' => $metadatos['hash'],
                'disk' => self::DISK,
                'categoria' => $categoria,
                'uploaded_by' => auth()->id(),
            ]);

            $this->auditService->registrar(
                'archivo.subido',
                $workflowId,
                null,
                [
                    'archivo_id' => $almacenado->id,
                    'categoria' => $categoria,
                    'tipo' => $archivableType,
                    'nombre' => $almacenado->nombre_original,
                ]
            );

            return $almacenado;
        });
    }

    public function eliminar(int $workflowId, int $id): void
    {
        DB::transaction(function () use ($workflowId, $id) {
            // Scope por workflow: evita IDOR cross-workflow
            $archivo = WorkFlowArchivo::where('workflow_id', $workflowId)->findOrFail($id);

            ArchivoHelper::eliminarArchivo($archivo->ruta_almacenada, $archivo->disk);

            $this->auditService->registrar(
                'archivo.eliminado',
                $archivo->workflow_id,
                null,
                ['archivo_id' => $archivo->id, 'nombre' => $archivo->nombre_original]
            );

            $archivo->delete();
        });
    }

    public function obtenerRuta(int $workflowId, int $id): array
    {
        // Scope por workflow: evita IDOR cross-workflow
        $archivo = WorkFlowArchivo::where('workflow_id', $workflowId)->findOrFail($id);

        return [
            'disk' => $archivo->disk,
            'path' => $archivo->ruta_almacenada,
            'nombre_original' => $archivo->nombre_original,
            'mime_type' => $archivo->mime_type,
        ];
    }
}
