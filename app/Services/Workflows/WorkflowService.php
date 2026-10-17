<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowConexion;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkflowService
{
    public function __construct(
        private readonly WorkflowAuditService $auditService,
        private readonly WorkflowGrafoValidator $grafoValidator
    ) {}

    public function listar(array $filtros = [])
    {
        $query = Workflow::with([
            'creador:id,nombres,apellidos',
            'administrador:id,nombres,apellidos',
        ])->withCount([
            'nodos',
            'instancias',
            'tareasModulo',
            'tareasModulo as tareas_completadas_count' => fn ($q) => $q->where('estado', 'completada'),
        ]);

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['search'])) {
            $query->where(function ($q) use ($filtros) {
                $q->where('nombre', 'like', "%{$filtros['search']}%")
                    ->orWhere('descripcion', 'like', "%{$filtros['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 10), 1), 100));
    }

    public function obtenerConRelaciones(int $id): Workflow
    {
        return Workflow::with(['nodos', 'conexiones', 'creador:id,nombres,apellidos', 'settings'])
            ->findOrFail($id);
    }

    public function crear(array $datos): Workflow
    {
        return DB::transaction(function () use ($datos) {
            $workflow = Workflow::create([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'tiempo_finalizacion_horas' => $datos['tiempo_finalizacion_horas'] ?? null,
                'creador_user_id' => auth()->id(),
            ]);

            if (! empty($datos['administrador_user_id'])) {
                $workflow->update(['administrador_user_id' => $datos['administrador_user_id']]);
            }

            $this->auditService->registrar(
                'workflow.creado',
                $workflow->id,
                null,
                ['nombre' => $workflow->nombre]
            );

            return $workflow;
        });
    }

    public function actualizar(int $id, array $datos): Workflow
    {
        return DB::transaction(function () use ($id, $datos) {
            $workflow = Workflow::lockForUpdate()->findOrFail($id);
            if (isset($datos['estado']) && $datos['estado'] !== $workflow->estado) {
                $this->asegurarTransicion($workflow, $datos['estado']);
            }
            $workflow->update($datos);

            $this->auditService->registrar(
                'workflow.actualizado',
                $workflow->id,
                null,
                ['nombre' => $workflow->nombre, 'cambios' => $datos]
            );

            return $workflow;
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $workflow = Workflow::findOrFail($id);

            $this->auditService->registrar(
                'workflow.eliminado',
                $workflow->id,
                null,
                ['nombre' => $workflow->nombre]
            );

            $workflow->delete();
        });
    }

    public function duplicar(int $id): Workflow
    {
        return DB::transaction(function () use ($id) {
            $original = Workflow::with(['nodos', 'conexiones', 'settings'])->findOrFail($id);

            $copia = Workflow::create([
                'nombre' => "{$original->nombre} (copia)",
                'descripcion' => $original->descripcion,
                'tiempo_finalizacion_horas' => $original->tiempo_finalizacion_horas,
                'administrador_user_id' => $original->administrador_user_id,
                'creador_user_id' => auth()->id(),
                'estado' => 'borrador',
            ]);

            if ($original->settings) {
                $copia->settings()->create($original->settings->only([
                    'estrategia_asignacion',
                    'configuracion_asignacion_json',
                    'alertas_kpi_json',
                    'opciones_adicionales_json',
                ]));
            }

            $mapaNodos = [];
            foreach ($original->nodos as $nodo) {
                $nuevoNodo = $copia->nodos()->create($nodo->only([
                    'tipo', 'titulo', 'descripcion', 'posicion_x', 'posicion_y',
                    'configuracion_json', 'orden_ejecucion',
                ]));
                $mapaNodos[$nodo->id] = $nuevoNodo->id;
            }

            foreach ($original->conexiones as $conexion) {
                // Saltar conexiones huérfanas (nodos eliminados del canvas)
                $origenId = $mapaNodos[$conexion->nodo_origen_id] ?? null;
                $destinoId = $mapaNodos[$conexion->nodo_destino_id] ?? null;

                if (! $origenId || ! $destinoId) {
                    continue;
                }

                $copia->conexiones()->create([
                    'nodo_origen_id' => $origenId,
                    'nodo_destino_id' => $destinoId,
                    'etiqueta' => $conexion->etiqueta,
                    'condicion_json' => $conexion->condicion_json,
                ]);
            }

            $this->auditService->registrar(
                'workflow.duplicado',
                $copia->id,
                null,
                ['original_id' => $original->id, 'nombre' => $copia->nombre]
            );

            return $copia;
        });
    }

    public function cambiarEstado(int $id, string $estado): Workflow
    {
        return DB::transaction(function () use ($id, $estado) {
            $workflow = Workflow::lockForUpdate()->findOrFail($id);
            $this->asegurarTransicion($workflow, $estado);
            $estadoAnterior = $workflow->estado;
            $workflow->update(['estado' => $estado]);

            $this->auditService->registrar(
                'workflow.cambio_estado',
                $workflow->id,
                null,
                ['de' => $estadoAnterior, 'a' => $estado]
            );

            return $workflow;
        });
    }

    /**
     * M19-22: solo transiciones del ciclo de vida; M19-18: solo se activa un grafo completo.
     */
    private function asegurarTransicion(Workflow $workflow, string $estado): void
    {
        if (! in_array($estado, Workflow::TRANSICIONES_ESTADO[$workflow->estado] ?? [], true)) {
            throw new StateTransitionException("Un flujo {$workflow->estado} no puede pasar a {$estado}");
        }
        if ($estado === 'activo') {
            $this->grafoValidator->validarWorkflow($workflow);
        }
    }

    /**
     * El grafo no se toca con instancias en curso: rompería nodo_actual_id y workflow_nodos_instancia.
     * Lo usan el canvas y el CRUD de nodos sueltos (M19-14).
     */
    public function asegurarGrafoEditable(Workflow $workflow, string $que = 'los nodos'): void
    {
        if ($workflow->instancias()->where('estado', 'en_curso')->exists()) {
            throw new HttpException(422, "No se puede modificar {$que}: el workflow tiene instancias en curso");
        }
    }

    public function guardarNodosYconexiones(int $workflowId, array $nodos, array $conexiones): Workflow
    {
        return DB::transaction(function () use ($workflowId, $nodos, $conexiones) {
            $workflow = Workflow::findOrFail($workflowId);

            $this->asegurarGrafoEditable($workflow, 'el canvas');

            // M19-18: un borrador puede guardarse a medias; un workflow activo, solo con el grafo completo
            $this->grafoValidator->validar(
                collect($nodos)->pluck('tipo', 'client_id')->all(),
                array_map(fn ($c) => [$c['nodo_origen_id'], $c['nodo_destino_id']], $conexiones),
                $workflow->estado === 'activo'
            );

            // Los nodos que ya existen llegan como "nodo_{id}": se actualizan en vez de recrearse,
            // porque borrarlos arrastra en cascada sus tareas, checklists e historial de instancias
            $existentes = $workflow->nodos()->get()->keyBy('id');
            WorkflowConexion::where('workflow_id', $workflowId)->delete();

            $clientIdMap = [];
            foreach ($nodos as $nodoData) {
                $atributos = collect($nodoData)->except(['client_id'])->toArray();
                $atributos['orden_ejecucion'] ??= 0;
                $idExistente = preg_match('/^nodo_(\d+)$/', $nodoData['client_id'], $m) ? (int) $m[1] : null;

                if ($idExistente && $existentes->has($idExistente)) {
                    $nodo = $existentes->get($idExistente);
                    $nodo->update($atributos);
                } else {
                    $nodo = $workflow->nodos()->create($atributos);
                }
                $clientIdMap[$nodoData['client_id']] = $nodo->id;
            }

            WorkflowNodo::where('workflow_id', $workflowId)->whereNotIn('id', array_values($clientIdMap))->delete();

            // Las conexiones solo pueden apuntar a nodos de este mismo canvas (client_id)
            foreach ($conexiones as $conexionData) {
                $origenId = $clientIdMap[$conexionData['nodo_origen_id']] ?? null;
                $destinoId = $clientIdMap[$conexionData['nodo_destino_id']] ?? null;

                if ($origenId && $destinoId) {
                    $workflow->conexiones()->create([
                        'nodo_origen_id' => $origenId,
                        'nodo_destino_id' => $destinoId,
                        'etiqueta' => $conexionData['etiqueta'] ?? null,
                        'condicion_json' => $conexionData['condicion_json'] ?? null,
                    ]);
                }
            }

            $this->auditService->registrar(
                'workflow.canvas_guardado',
                $workflow->id,
                null,
                ['total_nodos' => count($nodos), 'total_conexiones' => count($conexiones)]
            );

            return $workflow->load(['nodos', 'conexiones']);
        });
    }
}
