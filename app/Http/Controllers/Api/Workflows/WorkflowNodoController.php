<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreWorkflowNodoRequest;
use App\Http\Requests\Workflows\UpdateWorkflowNodoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Http\Request;

/**
 * Controlador de Nodos de Workflows — CRUD de nodos individuales.
 */
class WorkflowNodoController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Workflows -> Workflows -> Mostrar')->only(['index']);
        $this->middleware('can:Workflows -> Workflows -> Editar')->except(['index']);
    }

    public function index(int $workflowId)
    {
        try {
            $nodos = WorkflowNodo::where('workflow_id', $workflowId)
                ->orderBy('orden_ejecucion')
                ->get();
            return $this->successResponse($nodos, 'Nodos obtenidos correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener nodos', $e->getMessage());
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
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al crear nodo', $e->getMessage());
        }
    }

    public function update(UpdateWorkflowNodoRequest $request, int $workflowId, int $id)
    {
        try {
            $nodo = WorkflowNodo::where('workflow_id', $workflowId)->findOrFail($id);
            $nodo->update($request->validated());
            return $this->successResponse($nodo, 'Nodo actualizado correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar nodo', $e->getMessage());
        }
    }

    public function destroy(int $workflowId, int $id)
    {
        try {
            $nodo = WorkflowNodo::where('workflow_id', $workflowId)->findOrFail($id);
            $nodo->delete();
            return $this->successResponse(null, 'Nodo eliminado correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar nodo', $e->getMessage());
        }
    }
}
