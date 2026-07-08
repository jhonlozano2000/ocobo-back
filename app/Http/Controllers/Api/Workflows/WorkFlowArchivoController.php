<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreWorkFlowArchivoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Workflows\WorkFlowArchivoService;
use Illuminate\Support\Facades\Storage;

class WorkFlowArchivoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkFlowArchivoService $archivoService
    ) {
        $this->middleware('can:Workflows -> Archivos -> Subir')->only(['store']);
        $this->middleware('can:Workflows -> Archivos -> Descargar')->only(['download']);
        $this->middleware('can:Workflows -> Archivos -> Eliminar')->only(['destroy']);
    }

    public function index(int $workflowId)
    {
        try {
            $archivableType = request('archivable_type');
            $archivableId = request('archivable_id') ? (int) request('archivable_id') : null;

            $archivos = $this->archivoService->listar(
                $workflowId,
                $archivableType,
                $archivableId
            );
            return $this->successResponse($archivos, 'Archivos obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener archivos', $e->getMessage());
        }
    }

    public function store(StoreWorkFlowArchivoRequest $request, int $workflowId)
    {
        try {
            $data = $request->validated();

            $typeMap = [
                'nodo' => 'App\Models\Workflows\WorkflowNodo',
                'instancia' => 'App\Models\Workflows\WorkflowInstancia',
                'workflow' => 'App\Models\Workflows\Workflow',
            ];

            $archivo = $this->archivoService->subir(
                $request->file('archivo'),
                $workflowId,
                $typeMap[$data['archivable_type']] ?? 'App\Models\Workflows\Workflow',
                (int) $data['archivable_id'],
                $data['categoria'] ?? 'adjunto'
            );
            return $this->successResponse($archivo, 'Archivo subido correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir archivo', $e->getMessage());
        }
    }

    public function download(int $workflowId, int $archivoId)
    {
        try {
            $ruta = $this->archivoService->obtenerRuta($archivoId);

            if (!Storage::disk($ruta['disk'])->exists($ruta['path'])) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return Storage::disk($ruta['disk'])->download(
                $ruta['path'],
                $ruta['nombre_original'],
                ['Content-Type' => $ruta['mime_type']]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Archivo no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar archivo', $e->getMessage());
        }
    }

    public function destroy(int $workflowId, int $archivoId)
    {
        try {
            $this->archivoService->eliminar($archivoId);
            return $this->successResponse(null, 'Archivo eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar archivo', $e->getMessage());
        }
    }
}
