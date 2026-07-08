<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
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
            return $this->errorResponse('Error al obtener nodos', $e->getMessage());
        }
    }

    public function store(Request $request, int $workflowId)
    {
        try {
            $data = $request->validate([
                'tipo' => 'required|in:inicio,tarea,condicion,notificacion,fin',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'posicion_x' => 'required|numeric',
                'posicion_y' => 'required|numeric',
                'configuracion_json' => 'nullable|json',
            ]);

            $data['workflow_id'] = $workflowId;
            $nodo = WorkflowNodo::create($data);
            return $this->successResponse($nodo, 'Nodo creado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear nodo', $e->getMessage());
        }
    }

    public function update(Request $request, int $workflowId, int $id)
    {
        try {
            $nodo = WorkflowNodo::where('workflow_id', $workflowId)->findOrFail($id);
            $data = $request->validate([
                'titulo' => 'sometimes|string|max:255',
                'descripcion' => 'nullable|string',
                'posicion_x' => 'sometimes|numeric',
                'posicion_y' => 'sometimes|numeric',
                'configuracion_json' => 'nullable|json',
            ]);
            $nodo->update($data);
            return $this->successResponse($nodo, 'Nodo actualizado correctamente');
        } catch (\Exception $e) {
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
            return $this->errorResponse('Error al eliminar nodo', $e->getMessage());
        }
    }
}
