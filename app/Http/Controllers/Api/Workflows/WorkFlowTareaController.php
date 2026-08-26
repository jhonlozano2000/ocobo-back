<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Exceptions\Workflows\UncompletedChecklistException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\AsignarWorkFlowTareaRequest;
use App\Http\Requests\Workflows\CambiarEstadoWorkFlowTareaRequest;
use App\Http\Requests\Workflows\StoreWorkFlowTareaRequest;
use App\Http\Requests\Workflows\UpdateWorkFlowTareaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Workflows\WorkFlowTareaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class WorkFlowTareaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkFlowTareaService $tareaService
    ) {
        $this->middleware('can:Workflows -> Tareas -> Listar')->only(['index', 'show']);
        $this->middleware('can:Workflows -> Tareas -> Crear')->only(['store']);
        $this->middleware('can:Workflows -> Tareas -> Editar')->only(['update', 'cambiarEstado']);
        $this->middleware('can:Workflows -> Tareas -> Eliminar')->only(['destroy']);
        $this->middleware('can:Workflows -> Tareas -> Asignar')->only(['asignar']);
    }

    public function index(int $workflowId, int $nodoId)
    {
        try {
            $this->tareaService->verificarVencimientosPorNodo($nodoId);

            $instanciaId = request('instancia_id');
            $tareas = $this->tareaService->listar($nodoId, $instanciaId ? (int) $instanciaId : null);
            return $this->successResponse($tareas, 'Tareas obtenidas correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener tareas', $e->getMessage());
        }
    }

    public function store(StoreWorkFlowTareaRequest $request, int $workflowId, int $nodoId)
    {
        try {
            $instanciaId = $request->input('instancia_id');
            $tarea = $this->tareaService->crear(
                $nodoId,
                $request->validated(),
                $instanciaId ? (int) $instanciaId : null
            );
            return $this->successResponse($tarea, 'Tarea creada correctamente', 201);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al crear tarea', $e->getMessage());
        }
    }

    public function show(int $workflowId, int $nodoId, int $tareaId)
    {
        try {
            $tarea = \App\Models\Workflows\WorkFlowTarea::with(['responsable:id,nombres,apellidos', 'checklists'])
                ->where('nodo_id', $nodoId)
                ->findOrFail($tareaId);
            $tarea = $this->tareaService->verificarVencimientoAlCargar($tarea);
            return $this->successResponse($tarea, 'Tarea obtenida correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener tarea', $e->getMessage(), 404);
        }
    }

    public function update(UpdateWorkFlowTareaRequest $request, int $workflowId, int $nodoId, int $tareaId)
    {
        try {
            $tarea = $this->tareaService->actualizar($tareaId, $request->validated());
            return $this->successResponse($tarea, 'Tarea actualizada correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar tarea', $e->getMessage());
        }
    }

    public function destroy(int $workflowId, int $nodoId, int $tareaId)
    {
        try {
            $this->tareaService->eliminar($tareaId);
            return $this->successResponse(null, 'Tarea eliminada correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar tarea', $e->getMessage());
        }
    }

    public function asignar(AsignarWorkFlowTareaRequest $request, int $workflowId, int $nodoId, int $tareaId)
    {
        try {
            $tarea = $this->tareaService->asignar(
                $tareaId,
                $request->validated()['responsable_usuario_id']
            );
            return $this->successResponse($tarea, 'Tarea asignada correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al asignar tarea', $e->getMessage());
        }
    }

    public function cambiarEstado(CambiarEstadoWorkFlowTareaRequest $request, int $workflowId, int $nodoId, int $tareaId)
    {
        try {
            $data = $request->validated();
            $tarea = $this->tareaService->cambiarEstado(
                $tareaId,
                $data['estado'],
                $data['resultado'] ?? []
            );
            return $this->successResponse($tarea, 'Estado de tarea actualizado correctamente');
        } catch (UncompletedChecklistException $e) {
            return $e->render();
        } catch (StateTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tarea no encontrada', null, 404);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al cambiar estado de tarea', $e->getMessage(), 500);
        }
    }

    public function reordenar(Request $request, int $workflowId, int $nodoId)
    {
        try {
            $request->validate([
                'tareas' => 'required|array',
                'tareas.*' => 'required|integer|exists:work_flow_tareas,id',
            ]);

            $tareas = $this->tareaService->reordenar($nodoId, $request->input('tareas'));
            return $this->successResponse($tareas, 'Tareas reordenadas correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al reordenar tareas', $e->getMessage());
        }
    }
}
