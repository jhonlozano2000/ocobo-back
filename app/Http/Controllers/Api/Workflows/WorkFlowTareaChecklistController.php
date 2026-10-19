<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Api\Workflows\Concerns\AutorizaWorkflowDeRuta;
use App\Http\Controllers\Api\Workflows\Concerns\RespondeErroresWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\ReordenarWorkFlowChecklistRequest;
use App\Http\Requests\Workflows\StoreWorkFlowTareaChecklistRequest;
use App\Http\Requests\Workflows\UpdateWorkFlowTareaChecklistRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\WorkFlowTarea;
use App\Models\Workflows\WorkFlowTareaChecklist;
use App\Services\Workflows\WorkflowAuditService;
use App\Services\Workflows\WorkFlowTareaService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class WorkFlowTareaChecklistController extends Controller
{
    use ApiResponseTrait, AutorizaWorkflowDeRuta, RespondeErroresWorkflow;

    public function __construct(
        private readonly WorkFlowTareaService $tareaService,
        private readonly WorkflowAuditService $auditService
    ) {
        $this->middleware('can:Workflows -> Tareas -> Listar')->only(['index']);
        $this->middleware('can:Workflows -> Tareas -> Editar')->only(['store', 'update', 'reordenar']);
        $this->middleware('can:Workflows -> Tareas -> Eliminar')->only(['destroy']);
        // M19-53: todo el checklist de la plantilla es diseño. Quien ejecuta marca el checklist de
        // su Tarea (M19-17, M19-34); marcar aquí cambiaría la plantilla de todas las instancias
        $this->middleware($this->workflowDeRuta('update'))->only(['store', 'update', 'destroy', 'reordenar']);
        $this->middleware($this->workflowDeRuta())->only(['index']);
    }

    /**
     * Listar checklists de una tarea (System B)
     * GET /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists
     */
    public function index(int $workflowId, int $nodoId, WorkFlowTarea $tarea)
    {
        try {
            return $this->successResponse(
                $tarea->checklists()->orderBy('orden')->get(),
                'Checklists obtenidos correctamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al obtener checklists', $e);
        }
    }

    /**
     * Agregar ítem a checklist (System B)
     * POST /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists
     */
    public function store(StoreWorkFlowTareaChecklistRequest $request, int $workflowId, int $nodoId, WorkFlowTarea $tarea)
    {
        try {
            $checklist = $tarea->checklists()->create($request->validated());
            $this->auditarDiseno('tarea.checklist_item_creado', $tarea, $checklist);

            return $this->successResponse($checklist, 'Ítem agregado correctamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al agregar ítem', $e);
        }
    }

    /**
     * Actualizar ítem de checklist (System B)
     * PUT /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists/{checklist}
     */
    public function update(UpdateWorkFlowTareaChecklistRequest $request, int $workflowId, int $nodoId, WorkFlowTarea $tarea, WorkFlowTareaChecklist $checklist)
    {
        try {
            // Ownership: el ítem debe pertenecer a la tarea de la ruta
            abort_unless($checklist->work_flow_tarea_id === $tarea->id, 404, 'Ítem no pertenece a la tarea');

            $checklist->update($request->validated());

            return $this->successResponse($checklist->fresh(), 'Ítem actualizado correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al actualizar ítem', $e);
        }
    }

    /**
     * Eliminar ítem de checklist (System B)
     * DELETE /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists/{checklist}
     */
    public function destroy(int $workflowId, int $nodoId, WorkFlowTarea $tarea, WorkFlowTareaChecklist $checklist)
    {
        try {
            // Ownership: el ítem debe pertenecer a la tarea de la ruta
            abort_unless($checklist->work_flow_tarea_id === $tarea->id, 404, 'Ítem no pertenece a la tarea');

            $checklist->delete();
            $this->auditarDiseno('tarea.checklist_item_eliminado', $tarea, $checklist);

            return $this->successResponse(null, 'Ítem eliminado correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al eliminar ítem', $e);
        }
    }

    /**
     * Reordenar checklists (System B)
     * PUT /api/workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/checklists/reordenar
     */
    public function reordenar(ReordenarWorkFlowChecklistRequest $request, int $workflowId, int $nodoId, WorkFlowTarea $tarea)
    {
        try {
            $data = $request->validated();
            $this->tareaService->reordenarChecklist($tarea, $data['orden']);

            return $this->successResponse(null, 'Checklists reordenados correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al reordenar checklists', $e);
        }
    }

    /** M19-15: altas y bajas de ítems son cambios de diseño de la plantilla. */
    private function auditarDiseno(string $accion, WorkFlowTarea $tarea, WorkFlowTareaChecklist $item): void
    {
        $this->auditService->registrar($accion, $tarea->nodo?->workflow_id, null, [
            'tarea_id' => $tarea->id,
            'item_id' => $item->id,
            'item' => $item->item_descripcion,
        ]);
    }
}
