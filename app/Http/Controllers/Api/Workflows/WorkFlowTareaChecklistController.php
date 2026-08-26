<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\ReordenarWorkFlowChecklistRequest;
use App\Http\Requests\Workflows\StoreWorkFlowTareaChecklistRequest;
use App\Http\Requests\Workflows\UpdateWorkFlowTareaChecklistRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\WorkFlowTarea;
use App\Models\Workflows\WorkFlowTareaChecklist;
use App\Services\Workflows\WorkFlowTareaService;
use Illuminate\Validation\Rule;

class WorkFlowTareaChecklistController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkFlowTareaService $tareaService
    ) {
        $this->middleware('can:Workflows -> Tareas -> Listar')->only(['index']);
        $this->middleware('can:Workflows -> Tareas -> Editar')->only(['store', 'update', 'reordenar']);
        $this->middleware('can:Workflows -> Tareas -> Eliminar')->only(['destroy']);
    }

    /**
     * Listar checklists de una tarea (System B)
     * GET /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists
     */
    public function index(WorkFlowTarea $tarea)
    {
        try {
            return $this->successResponse(
                $tarea->checklists()->orderBy('orden')->get(),
                'Checklists obtenidos correctamente'
            );
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener checklists', $e->getMessage());
        }
    }

    /**
     * Agregar ítem a checklist (System B)
     * POST /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists
     */
    public function store(StoreWorkFlowTareaChecklistRequest $request, WorkFlowTarea $tarea)
    {
        try {
            $checklist = $tarea->checklists()->create($request->validated());
            return $this->successResponse($checklist, 'Ítem agregado correctamente', 201);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al agregar ítem', $e->getMessage());
        }
    }

    /**
     * Actualizar ítem de checklist (System B)
     * PUT /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists/{checklist}
     */
    public function update(UpdateWorkFlowTareaChecklistRequest $request, WorkFlowTarea $tarea, WorkFlowTareaChecklist $checklist)
    {
        try {
            // Ownership: el ítem debe pertenecer a la tarea de la ruta
            abort_unless($checklist->work_flow_tarea_id === $tarea->id, 404, 'Ítem no pertenece a la tarea');

            $checklist->update($request->validated());
            return $this->successResponse($checklist->fresh(), 'Ítem actualizado correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar ítem', $e->getMessage());
        }
    }

    /**
     * Eliminar ítem de checklist (System B)
     * DELETE /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists/{checklist}
     */
    public function destroy(WorkFlowTarea $tarea, WorkFlowTareaChecklist $checklist)
    {
        try {
            // Ownership: el ítem debe pertenecer a la tarea de la ruta
            abort_unless($checklist->work_flow_tarea_id === $tarea->id, 404, 'Ítem no pertenece a la tarea');

            $checklist->delete();
            return $this->successResponse(null, 'Ítem eliminado correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar ítem', $e->getMessage());
        }
    }

    /**
     * Reordenar checklists (System B)
     * PUT /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists/reordenar
     */
    public function reordenar(ReordenarWorkFlowChecklistRequest $request, WorkFlowTarea $tarea)
    {
        try {
            $data = $request->validated();
            $this->tareaService->reordenarChecklist($tarea, $data['orden']);
            return $this->successResponse(null, 'Checklists reordenados correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al reordenar checklists', $e->getMessage());
        }
    }
}
