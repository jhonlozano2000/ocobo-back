<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreChecklistItemRequest;
use App\Http\Requests\Workflows\UpdateChecklistItemRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\TareaChecklist;
use App\Services\Workflows\TareaService;

/**
 * Controlador de Ítems de Lista de Verificación de Tareas
 */
class TareaChecklistController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TareaService $tareaService
    ) {
    }

    /**
     * Listar checklists de una tarea
     * GET /api/workflow-tareas/{tarea}/checklists
     */
    public function index(Tarea $tarea)
    {
        try {
            return $this->successResponse(
                $tarea->checklists()->orderBy('orden')->get(),
                'Checklists obtenidos correctamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener checklists', $e->getMessage());
        }
    }

    /**
     * Agregar ítem a checklist
     * POST /api/workflow-tareas/{tarea}/checklists
     */
    public function store(StoreChecklistItemRequest $request, Tarea $tarea)
    {
        try {
            $checklist = $tarea->checklists()->create($request->validated());
            return $this->successResponse($checklist, 'Ítem agregado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar ítem', $e->getMessage());
        }
    }

    /**
     * Actualizar ítem de checklist
     * PUT /api/workflow-tareas/{tarea}/checklists/{checklist}
     */
    public function update(UpdateChecklistItemRequest $request, Tarea $tarea, TareaChecklist $checklist)
    {
        try {
            $checklist->update($request->validated());
            return $this->successResponse($checklist->fresh(), 'Ítem actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar ítem', $e->getMessage());
        }
    }

    /**
     * Eliminar ítem de checklist
     * DELETE /api/workflow-tareas/{tarea}/checklists/{checklist}
     */
    public function destroy(Tarea $tarea, TareaChecklist $checklist)
    {
        try {
            $checklist->delete();
            return $this->successResponse(null, 'Ítem eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar ítem', $e->getMessage());
        }
    }

    /**
     * Reordenar checklists
     * PUT /api/workflow-tareas/{tarea}/checklists/reordenar
     */
    public function reordenar(Tarea $tarea)
    {
        try {
            $data = request()->validate([
                'orden' => 'required|array',
                'orden.*' => 'required|integer|exists:tarea_checklists,id',
            ]);
            $this->tareaService->reordenarChecklist($tarea, $data['orden']);
            return $this->successResponse(null, 'Checklists reordenados correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al reordenar checklists', $e->getMessage());
        }
    }
}
