<?php

namespace App\Services\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Exceptions\Workflows\UncompletedChecklistException;
use App\Models\Workflows\WorkFlowTarea;
use App\Models\Workflows\WorkFlowTareaChecklist;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use App\Services\Workflows\WorkflowEstadoMachine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkFlowTareaService
{
    public function __construct(
        private readonly WorkflowAuditService $auditService,
        private readonly WorkflowNotificationService $notificationService
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

            if (!empty($datos['checklists'])) {
                $checklistItems = [];
                foreach ($datos['checklists'] as $index => $item) {
                    $checklistItems[] = new WorkFlowTareaChecklist([
                        'item_descripcion' => $item['item_descripcion'] ?? $item,
                        'esta_completado' => $item['esta_completado'] ?? false,
                        'orden' => $item['orden'] ?? $index,
                    ]);
                }
                $tarea->checklists()->saveMany($checklistItems);
            }

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

            return $tarea->load('responsable:id,name')->load('checklists');
        });
    }

    public function actualizar(int $id, array $datos): WorkFlowTarea
    {
        return DB::transaction(function () use ($id, $datos) {
            $tarea = WorkFlowTarea::with('checklists')->findOrFail($id);

            if (isset($datos['tiempo_limite_horas'])) {
                $datos['fecha_limite'] = now()->addHours($datos['tiempo_limite_horas']);
            }

            if (isset($datos['estado'])) {
                if (!WorkflowEstadoMachine::puedeTransitar($tarea->estado, $datos['estado'])) {
                    throw new StateTransitionException(
                        "No se puede cambiar de '{$tarea->estado}' a '{$datos['estado']}'"
                    );
                }
            }

            $tarea->update($datos);

            if (isset($datos['checklists'])) {
                $incomingIds = collect($datos['checklists'])->pluck('id')->filter();
                $tarea->checklists()->whereNotIn('id', $incomingIds)->delete();

                foreach ($datos['checklists'] as $index => $item) {
                    $itemDescripcion = $item['item_descripcion'] ?? $item;
                    $estaCompletado = $item['esta_completado'] ?? false;
                    $orden = $item['orden'] ?? $index;

                    if (!empty($item['id'])) {
                        WorkFlowTareaChecklist::where('id', $item['id'])
                            ->where('work_flow_tarea_id', $tarea->id)
                            ->update([
                                'item_descripcion' => $itemDescripcion,
                                'esta_completado' => $estaCompletado,
                                'orden' => $orden,
                            ]);
                    } else {
                        $tarea->checklists()->create([
                            'item_descripcion' => $itemDescripcion,
                            'esta_completado' => $estaCompletado,
                            'orden' => $orden,
                        ]);
                    }
                }
            }

            $this->auditService->registrar(
                'tarea.actualizada',
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'titulo' => $tarea->titulo]
            );

            return $tarea->load('responsable:id,name')->load('checklists');
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

            $this->notificationService->notificarTareaAsignadaSistemaB($tarea, $responsableUserId);

            return $tarea->load('responsable:id,name');
        });
    }

    public function cambiarEstado(int $tareaId, string $estado, array $resultado = []): WorkFlowTarea
    {
        return DB::transaction(function () use ($tareaId, $estado, $resultado) {
            $tarea = WorkFlowTarea::with('checklists')->findOrFail($tareaId);

            if (!WorkflowEstadoMachine::puedeTransitar($tarea->estado, $estado)) {
                throw new StateTransitionException(
                    "No se puede cambiar de '{$tarea->estado}' a '{$estado}'"
                );
            }

            if ($estado === 'completada') {
                $pendingCount = $tarea->checklists->where('esta_completado', false)->count();
                if ($pendingCount > 0) {
                    throw new UncompletedChecklistException($pendingCount);
                }
            }

            $estadoAnterior = $tarea->estado;

            $datos = ['estado' => $estado];

            if ($resultado) {
                $datos['resultado_json'] = $resultado;
            }

            $tarea->update($datos);

            $this->auditService->registrar(
                'tarea.' . $estado,
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'estado_anterior' => $estadoAnterior]
            );

            return $tarea->load('responsable:id,name')->load('checklists');
        });
    }

    public function verificarVencimientosPorNodo(int $nodoId): void
    {
        $vencidas = WorkFlowTarea::with('nodo:id,workflow_id')
            ->where('nodo_id', $nodoId)
            ->whereIn('estado', ['pendiente', 'en_curso'])
            ->whereNotNull('fecha_limite')
            ->where('fecha_limite', '<', now())
            ->get(['id', 'nodo_id', 'instancia_id', 'estado', 'responsable_usuario_id', 'titulo']);

        foreach ($vencidas as $tarea) {
            $this->auditService->registrar(
                'tarea.vencida',
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'estado_anterior' => $tarea->estado]
            );

            $this->notificationService->notificarWorkFlowTareaVencida($tarea);
        }

        WorkFlowTarea::whereIn('id', $vencidas->pluck('id'))->update(['estado' => 'vencida']);
    }

    public function verificarVencimientoAlCargar(WorkFlowTarea $tarea): WorkFlowTarea
    {
        $fechaLimiteStr = $tarea->fecha_limite?->toDateTimeString();

        if (WorkflowEstadoMachine::necesitaVencimiento($tarea->estado, $fechaLimiteStr)) {
            $estadoAnterior = $tarea->estado;
            $tarea->update(['estado' => 'vencida']);

            $this->auditService->registrar(
                'tarea.vencida',
                $tarea->nodo->workflow_id,
                $tarea->instancia_id,
                ['tarea_id' => $tarea->id, 'estado_anterior' => $estadoAnterior]
            );

            $this->notificationService->notificarWorkFlowTareaVencida($tarea);

            return $tarea->fresh('responsable:id,name');
        }

        return $tarea;
    }

    public function reordenarChecklist(WorkFlowTarea $tarea, array $orden): void
    {
        foreach ($orden as $index => $checklistId) {
            WorkFlowTareaChecklist::where('work_flow_tarea_id', $tarea->id)
                ->where('id', $checklistId)
                ->update(['orden' => $index]);
        }

        $this->auditService->registrar(
            'tarea.checklists.reordenados',
            $tarea->nodo->workflow_id,
            $tarea->instancia_id,
            ['tarea_id' => $tarea->id, 'orden' => $orden]
        );
    }
}
