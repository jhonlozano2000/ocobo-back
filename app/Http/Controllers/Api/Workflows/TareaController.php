<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreTareaRequest;
use App\Http\Requests\Workflows\UpdateTareaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Tarea;
use App\Services\Workflows\TareaService;

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
        // $this->middleware('can:...')->only(['index']);
    }

    /**
     * Listar tareas de un workflow
     * GET /api/workflow/{workflowId}/tareas
     */
    public function index(int $workflowId)
    {
        try {
            $tareas = Tarea::where('workflow_id', $workflowId)
                ->with(['propietarios', 'responsables', 'checklists'])
                ->orderBy('created_at', 'desc')
                ->paginate(min((int) request('per_page', 15), 100));

            return $this->successResponse($tareas, 'Tareas obtenidas correctamente');
        } catch (\Exception $e) {
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
            return $this->successResponse($tarea, 'Tarea obtenida correctamente');
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar tarea', $e->getMessage());
        }
    }

    /**
     * Eliminar una tarea
     * DELETE /api/workflow-tareas/{tarea}
     */
    public function destroy(Tarea $tarea)
    {
        try {
            $tarea->delete();
            return $this->successResponse(null, 'Tarea eliminada correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar tarea', $e->getMessage());
        }
    }

    /**
     * Completar una tarea (con validación de checklists)
     * POST /api/workflow-tareas/{tarea}/completar
     */
    public function completar(Tarea $tarea)
    {
        try {
            $tarea = $this->tareaService->completar($tarea);
            return $this->successResponse($tarea, 'Tarea completada correctamente');
        } catch (\App\Exceptions\Workflows\UncompletedChecklistException $e) {
            return $e->render();
        } catch (\Exception $e) {
            return $this->errorResponse('Error al completar tarea', $e->getMessage());
        }
    }
}
