<?php

namespace App\Services\Workflows;

use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkFlowTarea;
use Illuminate\Support\Collection;

/**
 * M19-17: Tarea es el sistema de tareas canónico; WorkFlowTarea queda como plantilla de diseño del nodo.
 * Cuando una instancia entra en un nodo, cada plantilla se convierte en una Tarea (con su checklist)
 * que aparece en "Mis tareas" del responsable.
 */
class TareasDeNodoService
{
    /** Estados que ya no bloquean el avance del nodo. */
    public const ESTADOS_CERRADOS = ['completada', 'cancelada'];

    public function __construct(
        private readonly WorkflowNotificationService $notificationService
    ) {}

    /**
     * @return Collection<int, Tarea>
     */
    public function crearDesdePlantillas(WorkflowInstancia $instancia, WorkflowNodo $nodo): Collection
    {
        $plantillas = WorkFlowTarea::with('checklists')
            ->where('nodo_id', $nodo->id)
            ->whereNull('instancia_id')
            ->orderBy('orden')
            ->get();

        return $plantillas->map(function (WorkFlowTarea $plantilla) use ($instancia, $nodo) {
            $horas = $plantilla->tiempo_limite_horas ?? $nodo->tiempo_limite_horas;
            $tarea = Tarea::create([
                'workflow_id' => $instancia->workflow_id,
                'instancia_id' => $instancia->id,
                'nodo_id' => $nodo->id,
                'plantilla_id' => $plantilla->id,
                'nombre' => $plantilla->titulo,
                'descripcion' => $plantilla->descripcion ?? $plantilla->instrucciones,
                'fecha_limite' => $horas ? now()->addHours($horas) : null,
                'estado' => 'pendiente',
            ]);

            // Quien inició la instancia es el propietario; el responsable sale de la plantilla o del nodo
            $responsableId = $plantilla->responsable_usuario_id ?? $nodo->responsable_usuario_id ?? $instancia->usuario_ejecuta_id;
            $tarea->propietarios()->attach($instancia->usuario_ejecuta_id);
            $tarea->responsables()->attach($responsableId);

            foreach ($plantilla->checklists as $item) {
                $tarea->checklists()->create([
                    'item_descripcion' => $item->item_descripcion,
                    'esta_completado' => false,
                    'orden' => $item->orden,
                ]);
            }

            if ($responsableId !== $instancia->usuario_ejecuta_id) {
                $this->notificationService->notificarTareaAsignadaSistemaA($tarea, $responsableId);
            }

            return $tarea;
        });
    }

    /**
     * Con $bloquear la lectura es FOR UPDATE: ve lo último confirmado aunque la transacción
     * ya tenga su snapshot (dos tareas completadas a la vez no se ven abiertas mutuamente).
     */
    public function abiertas(int $instanciaId, int $nodoId, bool $bloquear = false): int
    {
        return Tarea::where('instancia_id', $instanciaId)
            ->where('nodo_id', $nodoId)
            ->whereNotIn('estado', self::ESTADOS_CERRADOS)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->count();
    }

    /** Al detener o cancelar la instancia sus tareas abiertas dejan de tener sentido. */
    public function cancelarAbiertas(WorkflowInstancia $instancia): int
    {
        return Tarea::where('instancia_id', $instancia->id)
            ->whereNotIn('estado', self::ESTADOS_CERRADOS)
            ->update(['estado' => 'cancelada', 'updated_at' => now()]);
    }
}
