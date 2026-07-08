<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\EjecutarNodoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\WorkflowInstancia;
use App\Services\Workflows\WorkflowExecutionService;

/**
 * Controlador de Instancias de Workflows — Ejecución y monitoreo.
 *
 * ISO 27001 A.9.2.5: Verificación de permisos en cada acción.
 * ISO 27001 A.12.4.1: Auditoría vía WorkflowExecutionService.
 */
class WorkflowInstanciaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkflowExecutionService $executionService
    ) {
        $this->middleware('can:Workflows -> Instancias -> Consultar')->only(['index', 'show']);
        $this->middleware('can:Workflows -> Instancias -> Ejecutar')->only(['store', 'ejecutarNodo', 'detener']);
    }

    public function index(int $workflowId)
    {
        try {
            $instancias = WorkflowInstancia::with(['usuarioEjecuta:id,name', 'nodoActual', 'workflow:id,nombre'])
                ->where('workflow_id', $workflowId)
                ->orderBy('created_at', 'desc')
                ->paginate(request('per_page', 10));

            return $this->successResponse($instancias, 'Instancias obtenidas correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener instancias', $e->getMessage());
        }
    }

    public function store(int $workflowId)
    {
        try {
            $instancia = $this->executionService->iniciarInstancia($workflowId, auth()->id());
            return $this->successResponse($instancia, 'Instancia iniciada correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al iniciar instancia', $e->getMessage());
        }
    }

    public function show(int $workflowId, int $instanciaId)
    {
        try {
            $instancia = $this->executionService->obtenerInstancia($instanciaId);
            return $this->successResponse($instancia, 'Instancia obtenida correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener instancia', $e->getMessage(), 404);
        }
    }

    public function ejecutarNodo(EjecutarNodoRequest $request, int $workflowId, int $instanciaId)
    {
        try {
            $data = $request->validated();
            $instancia = $this->executionService->ejecutarNodo(
                $instanciaId,
                $data['nodo_id'],
                $data['resultado'] ?? []
            );
            return $this->successResponse($instancia, 'Nodo ejecutado correctamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al ejecutar nodo', $e->getMessage());
        }
    }

    public function detener(int $workflowId, int $instanciaId)
    {
        try {
            $request = request()->validate(['estado' => 'sometimes|in:detenida,cancelada']);
            $instancia = $this->executionService->detenerInstancia(
                $instanciaId,
                $request['estado'] ?? 'detenida'
            );
            return $this->successResponse($instancia, 'Instancia detenida correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al detener instancia', $e->getMessage());
        }
    }
}
