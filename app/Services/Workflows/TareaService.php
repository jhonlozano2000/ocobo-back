<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Exceptions\Workflows\UncompletedChecklistException;
use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\TareaChecklist;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

/**
 * ISO 27001 A.12.4.1: Auditoría en cada acción de escritura
 */
class TareaService
{
    /** pendiente, en_curso y el alias legado en_progreso (M19-12). */
    private const ESTADOS_VENCIBLES = ['pendiente', 'en_curso', 'en_progreso'];

    public function __construct(
        private readonly WorkflowAuditService $auditService,
        private readonly WorkflowNotificationService $notificationService,
        private readonly WorkflowExecutionService $executionService,
        private readonly TareasDeNodoService $tareasDeNodo
    ) {}

    public function store(array $data, User $user): Tarea
    {
        return DB::transaction(function () use ($data, $user) {
            Workflow::findOrFail($data['workflow_id']);

            $tarea = Tarea::create([
                'workflow_id' => $data['workflow_id'],
                'nombre' => $data['nombre'],
                'descripcion' => isset($data['descripcion'])
                    ? Purifier::clean($data['descripcion'])
                    : null,
                'fecha_limite' => $data['fecha_limite'] ?? null,
                'estado' => $data['estado'] ?? 'pendiente',
            ]);

            $tarea->propietarios()->attach($user->id);
            $tarea->responsables()->attach($user->id);

            if (! empty($data['checklists'])) {
                $checklistItems = [];
                foreach ($data['checklists'] as $index => $item) {
                    $checklistItems[] = new TareaChecklist([
                        'item_descripcion' => $item['item_descripcion'] ?? $item,
                        'esta_completado' => $item['esta_completado'] ?? false,
                        'orden' => $item['orden'] ?? $index,
                    ]);
                }
                $tarea->checklists()->saveMany($checklistItems);
            }

            $this->auditService->registrar(
                'tarea.creada',
                (int) $data['workflow_id'],
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function update(Tarea $tarea, array $data): Tarea
    {
        return DB::transaction(function () use ($tarea, $data) {
            $updateData = [];

            if (isset($data['nombre'])) {
                $updateData['nombre'] = $data['nombre'];
            }

            if (isset($data['descripcion'])) {
                $updateData['descripcion'] = Purifier::clean($data['descripcion']);
            }

            if (isset($data['fecha_limite'])) {
                $updateData['fecha_limite'] = $data['fecha_limite'];
            }

            if (isset($data['estado'])) {
                if (! WorkflowEstadoMachine::puedeTransitar($tarea->estado, $data['estado'])) {
                    throw new StateTransitionException(
                        "No se puede cambiar de '{$tarea->estado}' a '{$data['estado']}'"
                    );
                }

                if ($data['estado'] === 'completada') {
                    $pendingCount = $tarea->checklists()->where('esta_completado', false)->count();
                    if ($pendingCount > 0) {
                        throw new UncompletedChecklistException($pendingCount);
                    }
                    $updateData['completada_at'] = now();
                }
                $updateData['estado'] = $data['estado'];
            }

            if (! empty($updateData)) {
                $tarea->update($updateData);
            }

            if (($updateData['estado'] ?? null) === 'completada') {
                $this->avanzarNodoSiTerminado($tarea);
            }

            if (isset($data['propietarios'])) {
                $tarea->propietarios()->sync($data['propietarios']);
            }

            if (isset($data['responsables'])) {
                $tarea->responsables()->sync($data['responsables']);
            }

            if (isset($data['checklists'])) {
                $incomingIds = collect($data['checklists'])->pluck('id')->filter();
                $tarea->checklists()->whereNotIn('id', $incomingIds)->delete();

                foreach ($data['checklists'] as $index => $item) {
                    if (! empty($item['id'])) {
                        TareaChecklist::where('id', $item['id'])
                            ->where('tarea_id', $tarea->id)
                            ->update([
                                'item_descripcion' => $item['item_descripcion'] ?? $item,
                                'esta_completado' => $item['esta_completado'] ?? false,
                                'orden' => $item['orden'] ?? $index,
                            ]);
                    } else {
                        $tarea->checklists()->create([
                            'item_descripcion' => $item['item_descripcion'] ?? $item,
                            'esta_completado' => $item['esta_completado'] ?? false,
                            'orden' => $item['orden'] ?? $index,
                        ]);
                    }
                }
            }

            $this->auditService->registrar(
                'tarea.actualizada',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function destroy(Tarea $tarea): void
    {
        DB::transaction(function () use ($tarea) {
            $this->auditService->registrar(
                'tarea.eliminada',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            $tarea->delete();
        });
    }

    public function completar(Tarea $tarea): Tarea
    {
        return DB::transaction(function () use ($tarea) {
            $tarea = $tarea->fresh('checklists');

            if (! WorkflowEstadoMachine::puedeTransitar($tarea->estado, 'completada')) {
                throw new StateTransitionException(
                    "No se puede completar una tarea en estado '{$tarea->estado}'"
                );
            }

            $pendingCount = $tarea->checklists->where('esta_completado', false)->count();

            if ($pendingCount > 0) {
                throw new UncompletedChecklistException($pendingCount);
            }

            $tarea->update([
                'estado' => 'completada',
                'completada_at' => now(),
            ]);

            $this->auditService->registrar(
                'tarea.completada',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            $this->notificationService->notificarTareaCompletada($tarea, auth()->user());
            $this->avanzarNodoSiTerminado($tarea);

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    /**
     * M19-19: completar la última tarea abierta del nodo actual avanza la instancia. Cancelar no
     * avanza (alguien debe decidir); los nodos de condición tampoco, porque necesitan resultado.
     */
    private function avanzarNodoSiTerminado(Tarea $tarea): void
    {
        if (! $tarea->instancia_id || ! $tarea->nodo_id) {
            return;
        }

        // El bloqueo de la instancia serializa las tareas hermanas que se completan a la vez
        $instancia = WorkflowInstancia::with('nodoActual')->lockForUpdate()->find($tarea->instancia_id);
        if (! $instancia
            || $instancia->estado !== 'en_curso'
            || $instancia->nodo_actual_id !== $tarea->nodo_id
            || $instancia->nodoActual?->tipo === 'condicion'
            || $this->tareasDeNodo->abiertas($instancia->id, $tarea->nodo_id, true) > 0) {
            return;
        }

        $this->executionService->ejecutarNodo($instancia->id, $tarea->nodo_id, ['origen' => 'tareas_completadas']);
    }

    public function iniciar(Tarea $tarea): Tarea
    {
        return DB::transaction(function () use ($tarea) {
            if (! WorkflowEstadoMachine::puedeTransitar($tarea->estado, 'en_curso')) {
                throw new StateTransitionException(
                    "No se puede iniciar una tarea en estado '{$tarea->estado}'"
                );
            }

            $tarea->update(['estado' => 'en_curso']);

            $this->auditService->registrar(
                'tarea.iniciada',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function cancelar(Tarea $tarea): Tarea
    {
        return DB::transaction(function () use ($tarea) {
            if (! WorkflowEstadoMachine::puedeTransitar($tarea->estado, 'cancelada')) {
                throw new StateTransitionException(
                    "No se puede cancelar una tarea en estado '{$tarea->estado}'"
                );
            }

            $tarea->update(['estado' => 'cancelada']);

            $this->auditService->registrar(
                'tarea.cancelada',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function reactivar(Tarea $tarea): Tarea
    {
        return DB::transaction(function () use ($tarea) {
            if (! WorkflowEstadoMachine::puedeTransitar($tarea->estado, 'en_curso')) {
                throw new StateTransitionException(
                    "No se puede reactivar una tarea en estado '{$tarea->estado}'"
                );
            }

            $tarea->update([
                'estado' => 'en_curso',
                'fecha_limite' => null,
            ]);

            $this->auditService->registrar(
                'tarea.reactivada',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function verificarVencimientosPorWorkflow(int $workflowId): void
    {
        $this->vencer(Tarea::where('workflow_id', $workflowId));
    }

    public function verificarVencimientosDeUsuario(int $userId): void
    {
        $this->vencer(Tarea::where(function ($q) use ($userId) {
            $q->whereHas('propietarios', fn ($q) => $q->where('user_id', $userId))
                ->orWhereHas('responsables', fn ($q) => $q->where('user_id', $userId));
        }));
    }

    /** Barrido programado de todos los workflows (M19-13); devuelve cuántas tareas venció. */
    public function verificarVencimientosGlobales(): int
    {
        return $this->vencer(Tarea::query());
    }

    private function vencer(Builder $query): int
    {
        $vencidas = $query->with('responsables:id')
            // Estados desde los que la máquina permite transitar a vencida
            ->whereIn('estado', self::ESTADOS_VENCIBLES)
            ->whereNotNull('fecha_limite')
            ->where('fecha_limite', '<', now())
            ->get(['id', 'nombre', 'workflow_id']);

        foreach ($vencidas as $tarea) {
            $this->auditService->registrar('tarea.vencida', $tarea->workflow_id, null, [
                'tarea_id' => $tarea->id, 'nombre' => $tarea->nombre,
            ]);

            $this->notificationService->notificarTareaVencida($tarea);
        }

        Tarea::whereIn('id', $vencidas->pluck('id'))->update(['estado' => 'vencida']);

        return $vencidas->count();
    }

    public function verificarVencimientoAlCargar(Tarea $tarea): Tarea
    {
        // La transición a vencida debe ser legal según la máquina de estados
        if (WorkflowEstadoMachine::puedeTransitar($tarea->estado, 'vencida')
            && WorkflowEstadoMachine::necesitaVencimiento($tarea->estado, $tarea->fecha_limite)
        ) {
            $tarea->update(['estado' => 'vencida']);

            $this->auditService->registrar(
                'tarea.vencida',
                $tarea->workflow_id,
                null,
                ['tarea_id' => $tarea->id, 'nombre' => $tarea->nombre]
            );

            $this->notificationService->notificarTareaVencida($tarea);

            return $tarea->fresh(['propietarios', 'responsables', 'checklists', 'workflow']);
        }

        return $tarea;
    }

    public function addPropietario(Tarea $tarea, User $user): void
    {
        if (! $tarea->propietarios()->where('user_id', $user->id)->exists()) {
            $tarea->propietarios()->attach($user->id);
        }
    }

    public function addResponsable(Tarea $tarea, User $user): void
    {
        if (! $tarea->responsables()->where('user_id', $user->id)->exists()) {
            $tarea->responsables()->attach($user->id);
        }
    }

    public function removePropietario(Tarea $tarea, User $user): void
    {
        $tarea->propietarios()->detach($user->id);
    }

    public function removeResponsable(Tarea $tarea, User $user): void
    {
        $tarea->responsables()->detach($user->id);
    }

    public function reordenarChecklist(Tarea $tarea, array $orden): void
    {
        foreach ($orden as $index => $checklistId) {
            TareaChecklist::where('tarea_id', $tarea->id)
                ->where('id', $checklistId)
                ->update(['orden' => $index]);
        }

        $this->auditService->registrar(
            'tarea.checklists.reordenados',
            $tarea->workflow_id,
            null,
            ['tarea_id' => $tarea->id, 'orden' => $orden]
        );
    }
}
