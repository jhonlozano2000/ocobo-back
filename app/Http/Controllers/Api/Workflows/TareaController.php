<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Exceptions\Workflows\UncompletedChecklistException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreTareaRequest;
use App\Http\Requests\Workflows\UpdateTareaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Tarea;
use App\Services\Workflows\TareaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Controlador de Tareas de Workflow
 *
 * ISO 27001 A.9.1.1: Validación de acceso mediante middleware can:
 * ISO 27001 A.12.4.1: Auditoría en cada acción de escritura
 */
class TareaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TareaService $tareaService
    ) {
        $this->middleware('can:misTareas,' . Tarea::class)->only(['misTareas']);
        $this->middleware('can:viewAny,' . Tarea::class)->only(['index']);
        $this->middleware('can:create,' . Tarea::class)->only(['store']);
        $this->middleware('can:view,' . Tarea::class)->only(['show']);
        $this->middleware('can:update,' . Tarea::class)->only(['update']);
        $this->middleware('can:delete,' . Tarea::class)->only(['destroy']);
    }

    /**
     * Listar mis tareas (usuario autenticado como propietario o responsable)
     * GET /api/workflows/mis-tareas
     */
    public function misTareas()
    {
        try {
            $user = request()->user();

            $this->tareaService->verificarVencimientosDeUsuario($user->id);

            $tareas = Tarea::where(function ($q) use ($user) {
                $q->whereHas('propietarios', fn($q) => $q->where('user_id', $user->id))
                  ->orWhereHas('responsables', fn($q) => $q->where('user_id', $user->id));
            })
                ->with(['propietarios', 'responsables', 'checklists', 'workflow'])
                ->orderBy('created_at', 'desc')
                ->paginate(min((int) request('per_page', 15), 100));

            return $this->successResponse($tareas, 'Mis tareas obtenidas correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener mis tareas', $e->getMessage());
        }
    }

    /**
     * Listar tareas de un workflow
     * GET /api/workflow/{workflowId}/tareas
     */
    public function index(int $workflowId)
    {
        try {
            $this->tareaService->verificarVencimientosPorWorkflow($workflowId);

            $tareas = Tarea::where('workflow_id', $workflowId)
                ->with(['propietarios', 'responsables', 'checklists'])
                ->orderBy('created_at', 'desc')
                ->paginate(min((int) request('per_page', 15), 100));

            return $this->successResponse($tareas, 'Tareas obtenidas correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener tareas', $e->getMessage());
        }
    }

    /**
     * Mostrar una tarea
     * GET /api/workflow-tareas/{tarea}
     */
    public function show(Tarea $tarea)
    {
        try {
            $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
            $tarea = $this->tareaService->verificarVencimientoAlCargar($tarea);
            return $this->successResponse($tarea, 'Tarea obtenida correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener tarea', $e->getMessage(), 404);
        }
    }

    /**
     * Crear una tarea
     * POST /api/workflow-tareas
     */
    public function store(StoreTareaRequest $request)
    {
        try {
            $tarea = $this->tareaService->store(
                $request->validated(),
                $request->user()
            );
            return $this->successResponse($tarea, 'Tarea creada correctamente', 201);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al crear tarea', $e->getMessage());
        }
    }

    /**
     * Actualizar una tarea
     * PUT /api/workflow-tareas/{tarea}
     */
    public function update(UpdateTareaRequest $request, Tarea $tarea)
    {
        try {
            $tarea = $this->tareaService->update($tarea, $request->validated());
            return $this->successResponse($tarea, 'Tarea actualizada correctamente');
        } catch (UncompletedChecklistException $e) {
            return $e->render();
        } catch (StateTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tarea no encontrada', null, 404);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar tarea', $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar una tarea
     * DELETE /api/workflow-tareas/{tarea}
     */
    public function destroy(Tarea $tarea)
    {
        try {
            $this->tareaService->destroy($tarea);
            return $this->successResponse(null, 'Tarea eliminada correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar tarea', $e->getMessage());
        }
    }

    /**
     * Completar una tarea (con validación de checklists)
     * POST /api/workflow-tareas/{tarea}/completar
     */
    public function completar(Tarea $tarea)
    {
        $this->authorize('completar', $tarea);

        try {
            $tarea = $this->tareaService->completar($tarea);
            return $this->successResponse($tarea, 'Tarea completada correctamente');
        } catch (UncompletedChecklistException $e) {
            return $e->render();
        } catch (StateTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al completar tarea', $e->getMessage(), 500);
        }
    }

    public function iniciar(Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        try {
            $tarea = $this->tareaService->iniciar($tarea);
            return $this->successResponse($tarea, 'Tarea iniciada correctamente');
        } catch (StateTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al iniciar tarea', $e->getMessage(), 500);
        }
    }

    public function cancelar(Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        try {
            $tarea = $this->tareaService->cancelar($tarea);
            return $this->successResponse($tarea, 'Tarea cancelada correctamente');
        } catch (StateTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al cancelar tarea', $e->getMessage(), 500);
        }
    }

    public function reactivar(Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        try {
            $tarea = $this->tareaService->reactivar($tarea);
            return $this->successResponse($tarea, 'Tarea reactivada correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al reactivar tarea', $e->getMessage());
        }
    }
}
