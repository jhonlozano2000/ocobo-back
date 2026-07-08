<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkflowConexion;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function __construct(
        private readonly WorkflowAuditService $auditService
    ) {}

    public function listar(array $filtros = [])
    {
        $query = Workflow::with('creador:id,nombres,apellidos')
            ->withCount('nodos', 'instancias');

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['search'])) {
            $query->where(function ($q) use ($filtros) {
                $q->where('nombre', 'like', "%{$filtros['search']}%")
                  ->orWhere('descripcion', 'like', "%{$filtros['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filtros['per_page'] ?? 10);
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

            if (!empty($datos['administrador_user_id'])) {
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
            $workflow = Workflow::findOrFail($id);
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
                $copia->conexiones()->create([
                    'nodo_origen_id' => $mapaNodos[$conexion->nodo_origen_id],
                    'nodo_destino_id' => $mapaNodos[$conexion->nodo_destino_id],
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
            $workflow = Workflow::findOrFail($id);
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

    public function guardarNodosYconexiones(int $workflowId, array $nodos, array $conexiones): Workflow
    {
        return DB::transaction(function () use ($workflowId, $nodos, $conexiones) {
            $workflow = Workflow::findOrFail($workflowId);

            WorkflowNodo::where('workflow_id', $workflowId)->delete();
            WorkflowConexion::where('workflow_id', $workflowId)->delete();

            foreach ($nodos as $nodoData) {
                $workflow->nodos()->create($nodoData);
            }

            foreach ($conexiones as $conexionData) {
                $workflow->conexiones()->create($conexionData);
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
