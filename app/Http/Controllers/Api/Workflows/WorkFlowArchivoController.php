<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Api\Workflows\Concerns\AutorizaWorkflowDeRuta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreWorkFlowArchivoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkFlowArchivo;
use App\Services\Workflows\WorkFlowArchivoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class WorkFlowArchivoController extends Controller
{
    use ApiResponseTrait, AutorizaWorkflowDeRuta;

    public function __construct(
        private readonly WorkFlowArchivoService $archivoService
    ) {
        $this->middleware('can:Workflows -> Archivos -> Ver')->only(['index']);
        $this->middleware('can:Workflows -> Archivos -> Subir')->only(['store']);
        $this->middleware('can:Workflows -> Archivos -> Descargar')->only(['download']);
        $this->middleware('can:Workflows -> Archivos -> Eliminar')->only(['destroy']);
        $this->middleware($this->workflowDeRuta())->except(['store', 'destroy']);
    }

    public function index(int $workflowId)
    {
        try {
            $alias = request('archivable_type');
            abort_if($alias && ! isset(StoreWorkFlowArchivoRequest::TIPOS[$alias]), 422, 'El tipo de entidad no es válido');
            $archivableType = $alias ? StoreWorkFlowArchivoRequest::TIPOS[$alias] : null;
            $archivableId = request('archivable_id') ? (int) request('archivable_id') : null;

            $archivos = $this->archivoService->listar(
                $workflowId,
                $archivableType,
                $archivableId
            );

            return $this->successResponse($archivos, 'Archivos obtenidos correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener archivos', $e->getMessage());
        }
    }

    public function store(StoreWorkFlowArchivoRequest $request, int $workflowId)
    {
        try {
            $data = $request->validated();
            // Los adjuntos del diseño (nodo/workflow) son del creador; los de una instancia los sube quien ejecuta
            if ($data['archivable_type'] !== 'instancia') {
                $this->authorize('update', Workflow::findOrFail($workflowId));
            }

            $archivo = $this->archivoService->subir(
                $request->file('archivo'),
                $workflowId,
                StoreWorkFlowArchivoRequest::TIPOS[$data['archivable_type']],
                (int) $data['archivable_id'],
                $data['categoria'] ?? 'adjunto'
            );

            return $this->successResponse($archivo, 'Archivo subido correctamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al subir archivo', $e->getMessage());
        }
    }

    public function download(int $workflowId, int $archivoId)
    {
        try {
            $ruta = $this->archivoService->obtenerRuta($workflowId, $archivoId);

            if (! Storage::disk($ruta['disk'])->exists($ruta['path'])) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return Storage::disk($ruta['disk'])->download(
                $ruta['path'],
                $ruta['nombre_original'],
                ['Content-Type' => $ruta['mime_type']]
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Archivo no encontrado', null, 404);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al descargar archivo', $e->getMessage());
        }
    }

    public function destroy(Request $request, int $workflowId, int $archivoId)
    {
        try {
            $archivo = WorkFlowArchivo::where('workflow_id', $workflowId)->findOrFail($archivoId);
            // Borra quien lo subió, o el creador del workflow / Administrador
            if ((int) $archivo->uploaded_by !== (int) $request->user()->id) {
                $this->authorize('update', Workflow::findOrFail($workflowId));
            }
            $this->archivoService->eliminar($workflowId, $archivoId);

            return $this->successResponse(null, 'Archivo eliminado correctamente');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Archivo no encontrado en este workflow', null, 404);
        } catch (\Exception $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar archivo', $e->getMessage());
        }
    }
}
