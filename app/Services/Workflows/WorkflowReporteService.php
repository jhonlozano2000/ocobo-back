<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkFlowTarea;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Support\Facades\DB;

class WorkflowReporteService
{
    public function resumen(int $workflowId): array
    {
        $tareasModulo = Tarea::where('workflow_id', $workflowId);
        $tareasNodo = WorkFlowTarea::whereHas('nodo', fn($q) => $q->where('workflow_id', $workflowId));

        return [
            'tareas_modulo' => [
                'total' => (clone $tareasModulo)->count(),
                'pendiente' => (clone $tareasModulo)->where('estado', 'pendiente')->count(),
                'en_curso' => (clone $tareasModulo)->where('estado', 'en_curso')->count(),
                'completada' => (clone $tareasModulo)->where('estado', 'completada')->count(),
                'vencida' => (clone $tareasModulo)->where('estado', 'vencida')->count(),
                'cancelada' => (clone $tareasModulo)->where('estado', 'cancelada')->count(),
                'tasa_completitud' => $this->tasaCompletitud($tareasModulo),
            ],
            'tareas_nodo' => [
                'total' => (clone $tareasNodo)->count(),
                'pendiente' => (clone $tareasNodo)->where('estado', 'pendiente')->count(),
                'en_curso' => (clone $tareasNodo)->where('estado', 'en_curso')->count(),
                'completada' => (clone $tareasNodo)->where('estado', 'completada')->count(),
                'vencida' => (clone $tareasNodo)->where('estado', 'vencida')->count(),
                'cancelada' => (clone $tareasNodo)->where('estado', 'cancelada')->count(),
                'tasa_completitud' => $this->tasaCompletitud($tareasNodo),
            ],
            'instancias' => [
                'total' => WorkflowInstancia::where('workflow_id', $workflowId)->count(),
                'en_curso' => WorkflowInstancia::where('workflow_id', $workflowId)->where('estado', 'en_curso')->count(),
                'completada' => WorkflowInstancia::where('workflow_id', $workflowId)->where('estado', 'completada')->count(),
                'detenida' => WorkflowInstancia::where('workflow_id', $workflowId)->where('estado', 'detenida')->count(),
                'cancelada' => WorkflowInstancia::where('workflow_id', $workflowId)->where('estado', 'cancelada')->count(),
            ],
            'tiempos_promedio' => [
                'tareas_modulo_horas' => $this->tiempoPromedioTareasModulo($workflowId),
                'tareas_nodo_horas' => $this->tiempoPromedioTareasNodo($workflowId),
                'instancias_horas' => $this->tiempoPromedioInstancias($workflowId),
            ],
        ];
    }

    public function tareasVencidasPorUsuario(int $workflowId): array
    {
        $moduloVencidas = DB::table('workflow_tareas')
            ->join('tarea_responsables', 'workflow_tareas.id', '=', 'tarea_responsables.tarea_id')
            ->join('users', 'tarea_responsables.user_id', '=', 'users.id')
            ->where('workflow_tareas.workflow_id', $workflowId)
            ->where('workflow_tareas.estado', 'vencida')
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.id', 'users.name')
            ->get();

        $nodoVencidas = DB::table('work_flow_tareas')
            ->join('work_flow_nodos', 'work_flow_tareas.nodo_id', '=', 'work_flow_nodos.id')
            ->join('users', 'work_flow_tareas.responsable_usuario_id', '=', 'users.id')
            ->where('work_flow_nodos.workflow_id', $workflowId)
            ->where('work_flow_tareas.estado', 'vencida')
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.id', 'users.name')
            ->get();

        $porUsuario = collect($moduloVencidas)->concat($nodoVencidas)
            ->groupBy('id')
            ->map(function ($items) {
                return (object) [
                    'id' => $items->first()->id,
                    'name' => $items->first()->name,
                    'total' => $items->sum('total'),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->toArray();

        return $porUsuario;
    }

    public function tendenciaMensual(int $workflowId, int $meses = 6): array
    {
        $instancias = WorkflowInstancia::where('workflow_id', $workflowId)
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '>=', now()->subMonths($meses))
            ->selectRaw("DATE_FORMAT(fecha_fin, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return $instancias->toArray();
    }

    private function tasaCompletitud($query): float
    {
        $total = (clone $query)->count();
        if ($total === 0) return 0.0;
        $completadas = (clone $query)->where('estado', 'completada')->count();
        return round(($completadas / $total) * 100, 1);
    }

    private function tiempoPromedioTareasModulo(int $workflowId): ?float
    {
        $avg = Tarea::where('workflow_id', $workflowId)
            ->whereNotNull('completada_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completada_at)) as promedio')
            ->value('promedio');

        return $avg ? round((float) $avg, 1) : null;
    }

    private function tiempoPromedioTareasNodo(int $workflowId): ?float
    {
        $avg = WorkFlowTarea::whereHas('nodo', fn($q) => $q->where('workflow_id', $workflowId))
            ->where('estado', 'completada')
            ->whereNotNull('updated_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as promedio')
            ->value('promedio');

        return $avg ? round((float) $avg, 1) : null;
    }

    private function tiempoPromedioInstancias(int $workflowId): ?float
    {
        $avg = WorkflowInstancia::where('workflow_id', $workflowId)
            ->whereNotNull('fecha_fin')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, fecha_inicio, fecha_fin)) as promedio')
            ->value('promedio');

        return $avg ? round((float) $avg, 1) : null;
    }
}
