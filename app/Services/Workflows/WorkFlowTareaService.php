<?php

namespace App\Services\Workflows;

use App\Models\Workflows\WorkFlowTarea;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkFlowTareaService
{
    public function __construct(
        private readonly WorkflowAuditService $auditService
    ) {}

    public function listar(int $nodoId, ?int $instanciaId = null): Collection
    {
        $query = WorkFlowTarea::where('nodo_id', $nodoId)
            ->with('responsable:id,name');

        if ($instanciaId) {
            $query->where('instancia_id', $instanciaId);
        }

        return $query->orderBy('orden')->orderBy('created_at')->get();
    }

    public function crear(int $nodoId, array $datos, ?int $instanciaId = null): WorkFlowTarea
    {
        return DB::transaction(function () use ($nodoId, $datos, $instanciaId) {
            $nodo = WorkflowNodo::findOrFail($nodoId);

            $tarea = WorkFlowTarea::create([
                'nodo_id' => $nodoId,
                'instancia_id' => $instanciaId,
                'responsable_usuario_id' => $datos['responsable_usuario_id'] ?? null,
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'] ?? null,
                'instrucciones' => $datos['instrucciones'] ?? null,
                'tiempo_limite_horas' => $datos['tiempo_limite_horas'] ?? null,
                'adjuntos_permitidos' => $datos['adjuntos_permitidos'] ?? false,
                'orden' => $datos['orden'] ?? 0,
            ]);

            if ($tarea->tiempo_limite_horas) {
                $tarea->update([
                    'fecha_limite' => now()->addHours($tarea->tiempo_limite_horas),
                ]);
            }

            $this->auditService->registrar(
                'tarea.creada',
                $nodo->workflow_id,
                $instanciaId,
                ['tarea_id' => $tarea->id, 'titulo' => $tarea->titulo]
            );

            return $tarea->load('responsable:id,name');
        });
    }

    public function actualizar(int $id, array $datos): WorkFlowTarea
    {
        return DB::transaction(function () use ($id, $datos) {
            $tarea = WorkFlowTarea::findOrFail($id);

            if (isset($datos['tiempo_limite_horas'])) {
                $datos['fecha_limite'] = now()->addHours($datos['tiempo_limite_horas']);
            }

            $tarea->update($datos);

            $this->auditService->registrar(
                'tarea.actualizada',
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'titulo' => $tarea->titulo]
            );

            return $tarea->load('responsable:id,name');
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $tarea = WorkFlowTarea::findOrFail($id);

            $this->auditService->registrar(
                'tarea.eliminada',
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'titulo' => $tarea->titulo]
            );

            $tarea->delete();
        });
    }

    public function reordenar(int $nodoId, array $tareaIds)
    {
        return DB::transaction(function () use ($nodoId, $tareaIds) {
            foreach ($tareaIds as $index => $tareaId) {
                WorkFlowTarea::where('nodo_id', $nodoId)
                    ->where('id', $tareaId)
                    ->update(['orden' => $index]);
            }

            return WorkFlowTarea::where('nodo_id', $nodoId)
                ->with('responsable:id,name')
                ->orderBy('orden')
                ->orderBy('created_at')
                ->get();
        });
    }

    public function asignar(int $tareaId, int $responsableUserId): WorkFlowTarea
    {
        return DB::transaction(function () use ($tareaId, $responsableUserId) {
            $tarea = WorkFlowTarea::findOrFail($tareaId);
            $tarea->update(['responsable_usuario_id' => $responsableUserId]);

            $this->auditService->registrar(
                'tarea.asignada',
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'responsable' => $responsableUserId]
            );

            return $tarea->load('responsable:id,name');
        });
    }

    public function cambiarEstado(int $tareaId, string $estado, array $resultado = []): WorkFlowTarea
    {
        return DB::transaction(function () use ($tareaId, $estado, $resultado) {
            $tarea = WorkFlowTarea::findOrFail($tareaId);
            $datos = ['estado' => $estado];

            if ($resultado) {
                $datos['resultado_json'] = $resultado;
            }

            if ($estado === 'completada') {
                $datos['fecha_limite'] = now();
            }

            $tarea->update($datos);

            $this->auditService->registrar(
                'tarea.' . $estado,
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'estado_anterior' => $tarea->getOriginal('estado')]
            );

            return $tarea->load('responsable:id,name');
        });
    }

    public function verificarVencimiento(): int
    {
        $actualizadas = WorkFlowTarea::where('estado', 'pendiente')
            ->whereNotNull('fecha_limite')
            ->where('fecha_limite', '<', now())
            ->update(['estado' => 'vencida']);

        if ($actualizadas > 0) {
            $this->auditService->registrar(
                'tareas.vencidas_masivamente',
                null,
                null,
                ['cantidad' => $actualizadas]
            );
        }

        return $actualizadas;
    }
}
