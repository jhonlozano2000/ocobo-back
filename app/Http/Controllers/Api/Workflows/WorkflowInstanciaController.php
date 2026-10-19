<?php

namespace App\Http\Controllers\Api\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Exceptions\Workflows\WorkflowException;
use App\Http\Controllers\Api\Workflows\Concerns\RespondeErroresWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\DetenerInstanciaRequest;
use App\Http\Requests\Workflows\DevolverNodoRequest;
use App\Http\Requests\Workflows\EjecutarNodoRequest;
use App\Http\Requests\Workflows\StoreInstanciaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\WorkflowInstancia;
use App\Services\Workflows\WorkflowExecutionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador de Instancias de Workflows — Ejecución y monitoreo.
 *
 * ISO 27001 A.9.2.5: Verificación de permisos en cada acción.
 * ISO 27001 A.12.4.1: Auditoría vía WorkflowExecutionService.
 */
class WorkflowInstanciaController extends Controller
{
    use ApiResponseTrait, RespondeErroresWorkflow;

    public function __construct(
        private readonly WorkflowExecutionService $executionService
    ) {
        $this->middleware('can:Workflows -> Instancias -> Consultar')->only(['index', 'show']);
        $this->middleware('can:Workflows -> Instancias -> Ejecutar')->only(['store', 'ejecutarNodo', 'detener', 'devolver']);
        // M19-52: antes de validar el request, para no revelar nada de una instancia ajena
        $this->middleware(function ($request, $next) {
            abort_unless(WorkflowInstancia::visiblesPara($request->user())->whereKey((int) $request->route('instancia'))->exists(), 404, 'Instancia no encontrada');

            return $next($request);
        })->only(['show', 'ejecutarNodo', 'detener', 'devolver']);
    }

    public function index(int $workflowId)
    {
        try {
            $instancias = WorkflowInstancia::with(['usuarioEjecuta:id,nombres,apellidos', 'nodoActual', 'workflow:id,nombre', 'radicado:id,num_radicado'])
                ->where('workflow_id', $workflowId)
                ->visiblesPara(auth()->user())
                ->orderBy('created_at', 'desc')
                ->paginate(min(max((int) request('per_page', 10), 1), 100));

            return $this->successResponse($instancias, 'Instancias obtenidas correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al obtener instancias', $e);
        }
    }

    public function store(StoreInstanciaRequest $request, int $workflowId)
    {
        try {
            $data = $request->validated();
            $instancia = $this->executionService->iniciarInstancia(
                $workflowId,
                auth()->id(),
                $data['origen_tipo'] ?? null,
                isset($data['origen_id']) ? (int) $data['origen_id'] : null
            );

            return $this->successResponse($instancia, 'Instancia iniciada correctamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al iniciar instancia', $e);
        }
    }

    public function show(int $workflowId, int $instanciaId)
    {
        try {
            $instancia = $this->executionService->obtenerInstancia($instanciaId);

            // Scope: la instancia debe pertenecer al workflow de la ruta
            if ($instancia->workflow_id !== $workflowId) {
                return $this->errorResponse('Instancia no encontrada en este workflow', null, 404);
            }

            return $this->successResponse($instancia, 'Instancia obtenida correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al obtener instancia', $e);
        }
    }

    public function ejecutarNodo(EjecutarNodoRequest $request, int $workflowId, int $instanciaId)
    {
        try {
            $data = $request->validated();
            $instancia = $this->executionService->ejecutarNodo(
                $instanciaId,
                $data['nodo_id'],
                $data['resultado'] ?? [],
                $workflowId
            );

            return $this->successResponse($instancia, 'Nodo ejecutado correctamente');
        } catch (StateTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Instancia no encontrada', null, 404);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al ejecutar nodo', $e);
        }
    }

    public function detener(DetenerInstanciaRequest $request, int $workflowId, int $instanciaId)
    {
        try {
            $data = $request->validated();
            $instancia = $this->executionService->detenerInstancia(
                $instanciaId,
                $data['estado'] ?? 'detenida',
                $workflowId
            );

            return $this->successResponse($instancia, 'Instancia detenida correctamente');
        } catch (WorkflowException $e) {
            return $this->errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al detener instancia', $e);
        }
    }

    /** M19-28: reproceso; la instancia vuelve a un paso ya completado y se registra el motivo. */
    public function devolver(DevolverNodoRequest $request, int $workflowId, int $instanciaId)
    {
        try {
            $data = $request->validated();
            $instancia = $this->executionService->devolverNodo(
                $instanciaId,
                (int) $data['nodo_origen_id'],
                (int) $data['nodo_destino_id'],
                $data['motivo'],
                $workflowId
            );

            return $this->successResponse($instancia, 'Instancia devuelta correctamente');
        } catch (WorkflowException $e) {
            return $this->errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorDesdeExcepcion('Error al devolver instancia', $e);
        }
    }
}
