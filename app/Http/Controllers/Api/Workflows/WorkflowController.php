<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Workflows\UpdateWorkflowRequest;
use App\Http\Requests\Workflows\StoreNodoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Workflows\WorkflowService;

/**
 * Controlador de Workflows — CRUD y control de estado de flujos.
 *
 * ISO 27001 A.9.1.1: Validación de acceso mediante middleware can: y FormRequest authorize().
 * ISO 27001 A.12.4.1: Auditoría en cada acción de escritura vía WorkflowService.
 */
class WorkflowController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkflowService $workflowService
    ) {
        $this->middleware('can:Workflows -> Workflows -> Listar')->only(['index']);
        $this->middleware('can:Workflows -> Workflows -> Mostrar')->only(['show']);
        $this->middleware('can:Workflows -> Workflows -> Crear')->only(['store']);
        $this->middleware('can:Workflows -> Workflows -> Editar')->only(['update', 'guardarCanvas', 'cambiarEstado']);
        $this->middleware('can:Workflows -> Workflows -> Eliminar')->only(['destroy']);
        $this->middleware('can:Workflows -> Workflows -> Crear')->only(['duplicar']);
    }

    public function index()
    {
        try {
            $workflows = $this->workflowService->listar(request()->all());
            return $this->successResponse($workflows, 'Flujos obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener flujos', $e->getMessage());
        }
    }

    public function store(StoreWorkflowRequest $request)
    {
        try {
            $workflow = $this->workflowService->crear($request->validated());
            return $this->successResponse($workflow, 'Flujo creado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear flujo', $e->getMessage());
        }
    }

    public function show(int $id)
    {
        try {
            $workflow = $this->workflowService->obtenerConRelaciones($id);
            return $this->successResponse($workflow, 'Flujo obtenido correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener flujo', $e->getMessage(), 404);
        }
    }

    public function update(UpdateWorkflowRequest $request, int $id)
    {
        try {
            $workflow = $this->workflowService->actualizar($id, $request->validated());
            return $this->successResponse($workflow, 'Flujo actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar flujo', $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->workflowService->eliminar($id);
            return $this->successResponse(null, 'Flujo eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar flujo', $e->getMessage());
        }
    }

    public function duplicar(int $id)
    {
        try {
            $workflow = $this->workflowService->duplicar($id);
            return $this->successResponse($workflow, 'Flujo duplicado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al duplicar flujo', $e->getMessage());
        }
    }

    public function cambiarEstado(int $id)
    {
        try {
            $request = request()->validate(['estado' => 'required|in:borrador,activo,inactivo,archivado']);
            $workflow = $this->workflowService->cambiarEstado($id, $request['estado']);
            return $this->successResponse($workflow, 'Estado actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cambiar estado', $e->getMessage());
        }
    }

    public function guardarCanvas(StoreNodoRequest $request, int $workflowId)
    {
        try {
            $data = $request->validated();
            $workflow = $this->workflowService->guardarNodosYconexiones(
                $workflowId,
                $data['nodos'],
                $data['conexiones']
            );
            return $this->successResponse($workflow, 'Canvas guardado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al guardar canvas', $e->getMessage());
        }
    }
}
