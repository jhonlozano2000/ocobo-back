<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Api\Workflows\Concerns\RespondeErroresWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\ReordenarChecklistRequest;
use App\Http\Requests\Workflows\StoreChecklistItemRequest;
use App\Http\Requests\Workflows\UpdateChecklistItemRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\TareaChecklist;
use App\Services\Workflows\TareaService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador de Ítems de Lista de Verificación de Tareas
 */
class TareaChecklistController extends Controller
{
    use ApiResponseTrait, RespondeErroresWorkflow;

    public function __construct(
        private readonly TareaService $tareaService
    ) {
        $this->middleware('can:Workflows -> Tareas -> Listar')->only(['index']);
        $this->middleware('can:Workflows -> Tareas -> Editar')->only(['store', 'update', 'reordenar']);
        $this->middleware('can:Workflows -> Tareas -> Eliminar')->only(['destroy']);
    }

    /**
     * Listar checklists de una tarea
     * GET /api/workflow-tareas/{tarea}/checklists
     */
    public function index(Tarea $tarea)
    {
        $this->authorize('view', $tarea);

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
     * Agregar ítem a checklist
     * POST /api/workflow-tareas/{tarea}/checklists
     */
    public function store(StoreChecklistItemRequest $request, Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        try {
            $checklist = $tarea->checklists()->create($request->validated());

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
     * Actualizar ítem de checklist
     * PUT /api/workflow-tareas/{tarea}/checklists/{checklist}
     */
    public function update(UpdateChecklistItemRequest $request, Tarea $tarea, TareaChecklist $checklist)
    {
        // M19-34: marcar/desmarcar es ejecución (propietario o responsable); cambiar el texto u orden es diseño
        $datos = array_filter($request->validated(), fn ($v) => $v !== null);
        $soloMarca = array_keys($datos) === ['esta_completado'];
        $this->authorize($soloMarca ? 'completar' : 'update', $tarea);

        try {
            // Ownership: el ítem debe pertenecer a la tarea de la ruta
            abort_unless($checklist->tarea_id === $tarea->id, 404, 'Ítem no pertenece a la tarea');

            $checklist->update($datos);

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
     * Eliminar ítem de checklist
     * DELETE /api/workflow-tareas/{tarea}/checklists/{checklist}
     */
    public function destroy(Tarea $tarea, TareaChecklist $checklist)
    {
        $this->authorize('update', $tarea);

        try {
            // Ownership: el ítem debe pertenecer a la tarea de la ruta
            abort_unless($checklist->tarea_id === $tarea->id, 404, 'Ítem no pertenece a la tarea');

            $checklist->delete();

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
     * Reordenar checklists
     * PUT /api/workflow-tareas/{tarea}/checklists/reordenar
     */
    public function reordenar(ReordenarChecklistRequest $request, Tarea $tarea)
    {
        $this->authorize('update', $tarea);

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
}
