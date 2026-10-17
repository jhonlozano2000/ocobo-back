<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Api\Workflows\Concerns\AutorizaWorkflowDeRuta;
use App\Http\Controllers\Api\Workflows\Concerns\RespondeErroresWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreWorkflowNodoRequest;
use App\Http\Requests\Workflows\UpdateWorkflowNodoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use App\Services\Workflows\WorkflowService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador de Nodos de Workflows — CRUD de nodos individuales.
 */
class WorkflowNodoController extends Controller
{
    use ApiResponseTrait, AutorizaWorkflowDeRuta, RespondeErroresWorkflow;

    public function __construct(private readonly WorkflowService $workflowService)
    {
        $this->middleware('can:Workflows -> Workflows -> Mostrar')->only(['index']);
        $this->middleware('can:Workflows -> Workflows -> Editar')->except(['index']);
        // Modificar el grafo es diseño: creador del workflow o Administrador
        $this->middleware($this->workflowDeRuta('update'))->except(['index']);
        // M19-14: ni altas, ni cambios, ni bajas de nodos bajo una ejecución viva
        $this->middleware(function ($request, $next) {
            $workflow = $request->route('workflow');
            $this->workflowService->asegurarGrafoEditable($workflow instanceof Workflow ? $workflow : Workflow::findOrFail($workflow));

            return $next($request);
        })->except(['index']);
    }

    public function index(int $workflowId)
    {
        try {
            $nodos = WorkflowNodo::where('workflow_id', $workflowId)
                ->orderBy('orden_ejecucion')
                ->get();

            return $this->successResponse($nodos, 'Nodos obtenidos correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al obtener nodos', $e);
        }
    }

    public function store(StoreWorkflowNodoRequest $request, int $workflowId)
    {
        try {
            $data = $request->validated();

            $data['workflow_id'] = $workflowId;
            $nodo = WorkflowNodo::create($data);

            return $this->successResponse($nodo, 'Nodo creado correctamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al crear nodo', $e);
        }
    }

    public function update(UpdateWorkflowNodoRequest $request, int $workflowId, int $id)
    {
        try {
            $nodo = WorkflowNodo::where('workflow_id', $workflowId)->findOrFail($id);
            $nodo->update($request->validated());

            return $this->successResponse($nodo, 'Nodo actualizado correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al actualizar nodo', $e);
        }
    }

    public function destroy(int $workflowId, int $id)
    {
        try {
            $nodo = WorkflowNodo::where('workflow_id', $workflowId)->findOrFail($id);
            $nodo->delete();

            return $this->successResponse(null, 'Nodo eliminado correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al eliminar nodo', $e);
        }
    }
}
