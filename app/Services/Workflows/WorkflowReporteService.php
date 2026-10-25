<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * KPI de un workflow. Desde M19-17 todas las tareas reales son Tarea: las que nacen de un nodo
 * (tienen instancia) y las manuales asociadas al flujo (sin instancia). WorkFlowTarea es solo la
 * plantilla de diseño del nodo, por eso no entra en los conteos.
 */
class WorkflowReporteService
{
    private const ESTADOS_TAREA = ['pendiente', 'en_curso', 'completada', 'vencida', 'cancelada'];

    private const ESTADOS_INSTANCIA = ['en_curso', 'completada', 'detenida', 'cancelada'];

    public const MAX_MESES = 24;

    public function resumen(int $workflowId): array
    {
        $manuales = fn () => Tarea::where('workflow_id', $workflowId)->whereNull('instancia_id');
        $deNodo = fn () => Tarea::where('workflow_id', $workflowId)->whereNotNull('instancia_id');

        $instancias = $this->conteoPorEstado(WorkflowInstancia::where('workflow_id', $workflowId), self::ESTADOS_INSTANCIA);

        return [
            'tareas_modulo' => $this->resumenTareas($manuales()),
            'tareas_nodo' => $this->resumenTareas($deNodo()),
            'instancias' => ['total' => array_sum($instancias)] + $instancias,
            'tiempos_promedio' => [
                'tareas_modulo_horas' => $this->horasPromedio($manuales()->whereNotNull('completada_at'), 'created_at', 'completada_at'),
                'tareas_nodo_horas' => $this->horasPromedio($deNodo()->whereNotNull('completada_at'), 'created_at', 'completada_at'),
                'instancias_horas' => $this->horasPromedio(
                    WorkflowInstancia::where('workflow_id', $workflowId)->whereNotNull('fecha_fin'), 'fecha_inicio', 'fecha_fin'
                ),
            ],
        ];
    }

    /**
     * Responsables con tareas vencidas del flujo, de más a menos.
     *
     * @return list<array{id: int, name: string, total: int}>
     */
    public function tareasVencidasPorUsuario(int $workflowId): array
    {
        return DB::table('workflow_tareas')
            ->join('tarea_responsables', 'workflow_tareas.id', '=', 'tarea_responsables.tarea_id')
            ->join('users', 'tarea_responsables.user_id', '=', 'users.id')
            ->where('workflow_tareas.workflow_id', $workflowId)
            ->where('workflow_tareas.estado', 'vencida')
            ->groupBy('users.id', 'users.nombres', 'users.apellidos')
            ->orderByDesc('total')
            ->orderBy('users.id')
            ->get(['users.id', 'users.nombres', 'users.apellidos', DB::raw('COUNT(DISTINCT workflow_tareas.id) as total')])
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'name' => trim($fila->nombres.' '.$fila->apellidos),
                'total' => (int) $fila->total,
            ])
            ->all();
    }

    /**
     * Instancias completadas por mes en los últimos $meses meses (incluido el actual), con los meses
     * sin completadas en cero para que la gráfica no se salte meses.
     *
     * @return list<array{mes: string, total: int}>
     */
    public function tendenciaMensual(int $workflowId, int $meses = 6): array
    {
        $meses = max(1, min(self::MAX_MESES, $meses));
        $desde = now()->startOfMonth()->subMonths($meses - 1);

        $porMes = WorkflowInstancia::where('workflow_id', $workflowId)
            ->where('fecha_fin', '>=', $desde)
            ->selectRaw("DATE_FORMAT(fecha_fin, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        return collect(range(0, $meses - 1))
            ->map(function (int $i) use ($desde, $porMes) {
                $mes = $desde->copy()->addMonths($i)->format('Y-m');

                return ['mes' => $mes, 'total' => (int) ($porMes[$mes] ?? 0)];
            })
            ->all();
    }

    /**
     * @return array<string, int|float>
     */
    private function resumenTareas(Builder $query): array
    {
        $porEstado = $this->conteoPorEstado($query, self::ESTADOS_TAREA);
        $total = array_sum($porEstado);

        return ['total' => $total] + $porEstado + [
            'tasa_completitud' => $total ? round($porEstado['completada'] / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  list<string>  $estados
     * @return array<string, int>
     */
    private function conteoPorEstado(Builder $query, array $estados): array
    {
        $conteos = $query->toBase()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return collect($estados)->mapWithKeys(fn ($estado) => [$estado => (int) ($conteos[$estado] ?? 0)])->all();
    }

    private function horasPromedio(Builder $query, string $inicio, string $fin): ?float
    {
        $promedio = $query->toBase()->selectRaw("AVG(TIMESTAMPDIFF(HOUR, {$inicio}, {$fin})) as promedio")->value('promedio');

        return $promedio === null ? null : round((float) $promedio, 1);
    }
}
