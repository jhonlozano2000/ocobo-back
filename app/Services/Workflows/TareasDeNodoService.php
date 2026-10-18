<?php

namespace App\Services\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkflowSetting;
use App\Models\Workflows\WorkFlowTarea;
use Illuminate\Support\Collection;

/**
 * M19-17: Tarea es el sistema de tareas canónico; WorkFlowTarea queda como plantilla de diseño del nodo.
 * Cuando una instancia entra en un nodo, cada plantilla se convierte en una Tarea (con su checklist)
 * que aparece en "Mis tareas" del responsable.
 */
class TareasDeNodoService
{
    /** Primer usuario activo de la lista, respetando el orden. */
    private function primerActivo(array $ids): ?int
    {
        $ids = array_values(array_filter($ids));
        $activos = $ids ? User::whereIn('id', $ids)->where('estado', 1)->pluck('id')->all() : [];

        foreach ($ids as $id) {
            if (in_array($id, $activos)) {
                return (int) $id;
            }
        }

        return null;
    }

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

        $settings = WorkflowSetting::where('workflow_id', $instancia->workflow_id)->first();

        return $plantillas->map(function (WorkFlowTarea $plantilla) use ($instancia, $nodo, $settings) {
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
            // (M19-23: si el diseñado ya está inactivo se pasa al siguiente, y al final a quien ejecuta).
            // M19-26: lo que el diseño no fija lo reparte la estrategia del flujo.
            $responsables = $this->responsables($plantilla, $nodo, $settings) ?: [$instancia->usuario_ejecuta_id];
            $tarea->propietarios()->attach($instancia->usuario_ejecuta_id);
            $tarea->responsables()->attach($responsables);

            foreach ($plantilla->checklists as $item) {
                $tarea->checklists()->create([
                    'item_descripcion' => $item->item_descripcion,
                    'esta_completado' => false,
                    'orden' => $item->orden,
                ]);
            }

            foreach (array_diff($responsables, [$instancia->usuario_ejecuta_id]) as $responsableId) {
                $this->notificationService->notificarTareaAsignadaSistemaA($tarea, $responsableId);
            }

            return $tarea;
        });
    }

    /**
     * @return list<int>
     */
    private function responsables(WorkFlowTarea $plantilla, WorkflowNodo $nodo, ?WorkflowSetting $settings): array
    {
        $fijo = $this->primerActivo([$plantilla->responsable_usuario_id, $nodo->responsable_usuario_id]);
        if ($fijo) {
            return [$fijo];
        }

        $estrategia = $settings?->estrategia_asignacion;
        if (! $settings || ! in_array($estrategia, WorkflowSetting::ESTRATEGIAS_CON_EQUIPO, true)) {
            return [];
        }

        $activos = $this->activos($settings->equipo());

        return $estrategia === 'autoasignar' ? $activos : array_filter([$this->siguienteEnFila($settings, $activos)]);
    }

    /**
     * Rotación: el siguiente activo después del último asignado. La fila de settings se bloquea
     * para que dos instancias que entran a la vez no le den la tarea a la misma persona.
     */
    private function siguienteEnFila(WorkflowSetting $settings, array $activos): ?int
    {
        if (! $activos) {
            return null;
        }

        $settings = WorkflowSetting::lockForUpdate()->find($settings->id);
        $config = $settings->configuracion_asignacion_json ?? [];
        $equipo = $settings->equipo();
        $ultimo = array_search($config['ultimo_asignado'] ?? null, $equipo, true);
        $orden = $ultimo === false ? $equipo : [...array_slice($equipo, $ultimo + 1), ...array_slice($equipo, 0, $ultimo + 1)];
        $siguiente = current(array_intersect($orden, $activos)) ?: null;

        $config['ultimo_asignado'] = $siguiente;
        $settings->update(['configuracion_asignacion_json' => $config]);

        return $siguiente;
    }

    /**
     * Activos de la lista, en su orden.
     *
     * @return list<int>
     */
    private function activos(array $ids): array
    {
        $activos = $ids ? User::whereIn('id', $ids)->where('estado', 1)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        return array_values(array_filter($ids, fn ($id) => in_array($id, $activos, true)));
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

    /** M19-28: al devolver la instancia, las tareas de los pasos que se rehacen se cancelan. */
    public function cancelarAbiertasDeNodos(WorkflowInstancia $instancia, array $nodoIds): int
    {
        return Tarea::where('instancia_id', $instancia->id)
            ->whereIn('nodo_id', $nodoIds)
            ->whereNotIn('estado', self::ESTADOS_CERRADOS)
            ->update(['estado' => 'cancelada', 'updated_at' => now()]);
    }

    /** Al detener o cancelar la instancia sus tareas abiertas dejan de tener sentido. */
    public function cancelarAbiertas(WorkflowInstancia $instancia): int
    {
        return Tarea::where('instancia_id', $instancia->id)
            ->whereNotIn('estado', self::ESTADOS_CERRADOS)
            ->update(['estado' => 'cancelada', 'updated_at' => now()]);
    }
}
