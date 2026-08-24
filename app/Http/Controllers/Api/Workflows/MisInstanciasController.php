<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\WorkflowInstancia;

class MisInstanciasController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Workflows -> Instancias -> Consultar');
    }

    public function __invoke()
    {
        try {
            $userId = auth()->id();

            $instancias = WorkflowInstancia::with([
                'workflow:id,nombre',
                'nodoActual:id,titulo',
                'usuarioEjecuta:id,name',
            ])
                ->where('usuario_ejecuta_id', $userId)
                ->orWhereIn('id', function ($q) use ($userId) {
                    $q->select('instancia_id')
                        ->from('workflow_nodos_instancia')
                        ->whereIn('nodo_id', function ($q2) use ($userId) {
                            $q2->select('nodo_id')
                                ->from('work_flow_tareas')
                                ->where('responsable_usuario_id', $userId);
                        });
                })
                ->orderBy('created_at', 'desc')
                ->paginate(request('per_page', 10));

            return $this->successResponse($instancias, 'Mis instancias obtenidas correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener instancias', $e->getMessage());
        }
    }
}
