<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio de Reportes y Estadísticas del Archivo.
 *
 * Genera reportes exportables en Excel/PDF para:
 * - Radicados recibidos/enviados por período y dependencia
 * - PQRS por estado y tipo (para informe DAFP)
 * - Préstamos del archivo por período
 * - Inventario documental (FUID) por dependencia
 * - Estadísticas de correspondencia por ventanilla
 *
 * Circular 004 DAFP — Reportes obligatorios.
 */
class ReportesService
{
    /**
     * Reporte de expedientes por dependencia y estado.
     */
    public function expedientes(array $filtros): array
    {
        $query = OfiArchivoExpediente::with(['dependencia', 'serieTrd']);

        if (! empty($filtros['dependencia_id'])) {
            $query->where('dependencia_id', $filtros['dependencia_id']);
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha_apertura', '>=', $filtros['fecha_inicio']);
        }

        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha_apertura', '<=', $filtros['fecha_fin']);
        }

        $expedientes = $query->get();

        return [
            'titulo' => 'Reporte de Expedientes',
            'fecha_generacion' => now()->toDateTimeString(),
            'total' => $expedientes->count(),
            'datos' => $expedientes->map(fn ($e) => [
                'numero' => $e->numero_expediente,
                'nombre' => $e->nombre_expediente,
                'dependencia' => $e->dependencia->nom_organico ?? '—',
                'serie' => $e->serieTrd->nom ?? '—',
                'estado' => $e->estado,
                'fecha_apertura' => $e->fecha_apertura?->format('d/m/Y'),
                'total_folios' => $e->total_folios_elec,
            ])->toArray(),
            'resumen' => [
                'abiertos' => $expedientes->where('estado', 'Abierto')->count(),
                'cerrados' => $expedientes->where('estado', 'Cerrado')->count(),
                'transferidos' => $expedientes->where('estado', 'Transferido')->count(),
            ],
        ];
    }

    /**
     * Reporte de préstamos por período.
     */
    public function prestamos(array $filtros): array
    {
        $query = OfiArchivoPrestamo::with(['expediente', 'solicitante']);

        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha_prestamo', '>=', $filtros['fecha_inicio']);
        }

        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha_prestamo', '<=', $filtros['fecha_fin']);
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        $prestamos = $query->get();

        return [
            'titulo' => 'Reporte de Préstamos de Archivo',
            'fecha_generacion' => now()->toDateTimeString(),
            'total' => $prestamos->count(),
            'datos' => $prestamos->map(fn ($p) => [
                'expediente' => $p->expediente->numero_expediente ?? '—',
                'solicitante' => trim(($p->solicitante->nombres ?? '').' '.($p->solicitante->apellidos ?? '')),
                'fecha_prestamo' => $p->fecha_prestamo->format('d/m/Y'),
                'fecha_devolucion' => $p->fecha_devolucion_esperada->format('d/m/Y'),
                'estado' => $p->estado,
            ])->toArray(),
            'resumen' => [
                'activos' => $prestamos->where('estado', 'prestado')->count(),
                'devueltos' => $prestamos->where('estado', 'devuelto')->count(),
                'vencidos' => $prestamos->where('estado', 'vencido')->count(),
            ],
        ];
    }

    /**
     * Reporte de transferencias documentales.
     */
    public function transferencias(array $filtros): array
    {
        $query = OfiArchivoTransferencia::with(['expediente', 'responsableOrigen']);

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha_transferencia', '>=', $filtros['fecha_inicio']);
        }

        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha_transferencia', '<=', $filtros['fecha_fin']);
        }

        $transferencias = $query->get();

        return [
            'titulo' => 'Reporte de Transferencias Documentales',
            'fecha_generacion' => now()->toDateTimeString(),
            'total' => $transferencias->count(),
            'datos' => $transferencias->map(fn ($t) => [
                'expediente' => $t->expediente->numero_expediente ?? '—',
                'tipo' => $t->tipo,
                'origen' => $t->origen,
                'destino' => $t->destino,
                'fecha' => $t->fecha_transferencia->format('d/m/Y'),
                'estado' => $t->estado,
            ])->toArray(),
            'resumen' => [
                'primarias' => $transferencias->where('tipo', 'primaria')->count(),
                'secundarias' => $transferencias->where('tipo', 'secundaria')->count(),
            ],
        ];
    }

    /**
     * Estadísticas generales del módulo de archivo.
     * Incluye sparklines, charts y datos históricos para el dashboard.
     */
    public function estadisticasGenerales(): array
    {
        return Cache::remember('archivo_estadisticas_generales', now()->addMinutes(5), function () {
            $meses = collect();
            for ($i = 5; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $meses->push([
                    'label' => $fecha->format('M'),
                    'abiertos' => OfiArchivoExpediente::where('estado', 'Abierto')
                        ->whereMonth('fecha_apertura', $fecha->month)
                        ->whereYear('fecha_apertura', $fecha->year)
                        ->count(),
                    'cerrados' => OfiArchivoExpediente::where('estado', 'Cerrado')
                        ->whereMonth('fecha_apertura', $fecha->month)
                        ->whereYear('fecha_apertura', $fecha->year)
                        ->count(),
                    'prestamos' => OfiArchivoPrestamo::whereMonth('fecha_prestamo', $fecha->month)
                        ->whereYear('fecha_prestamo', $fecha->year)
                        ->count(),
                    'prestamos_activos' => OfiArchivoPrestamo::activos()
                        ->whereMonth('fecha_prestamo', $fecha->month)
                        ->whereYear('fecha_prestamo', $fecha->year)
                        ->count(),
                    'prestamos_devueltos' => OfiArchivoPrestamo::where('estado', 'devuelto')
                        ->whereMonth('fecha_devolucion_real', $fecha->month)
                        ->whereYear('fecha_devolucion_real', $fecha->year)
                        ->count(),
                    'transferencias' => OfiArchivoTransferencia::whereMonth('fecha_transferencia', $fecha->month)
                        ->whereYear('fecha_transferencia', $fecha->year)
                        ->count(),
                ]);
            }

            return [
                'expedientes' => [
                    'total' => OfiArchivoExpediente::count(),
                    'abiertos' => OfiArchivoExpediente::where('estado', 'Abierto')->count(),
                    'cerrados' => OfiArchivoExpediente::where('estado', 'Cerrado')->count(),
                    'transferidos' => OfiArchivoExpediente::where('estado', 'Transferido')->count(),
                ],
                'prestamos' => [
                    'activos' => OfiArchivoPrestamo::activos()->count(),
                    'vencidos' => OfiArchivoPrestamo::vencidos()->count(),
                ],
                'transferencias' => [
                    'pendientes' => OfiArchivoTransferencia::where('estado', 'pendiente')->count(),
                    'completadas_hoy' => OfiArchivoTransferencia::where('estado', 'completada')
                        ->whereDate('fecha_transferencia', today())->count(),
                ],
                'sparklines' => [
                    'expedientes_abiertos' => $meses->pluck('abiertos')->toArray(),
                    'expedientes_cerrados' => $meses->pluck('cerrados')->toArray(),
                    'prestamos' => $meses->pluck('prestamos')->toArray(),
                    'prestamos_activos' => $meses->pluck('prestamos_activos')->toArray(),
                    'transferencias' => $meses->pluck('transferencias')->toArray(),
                    'labels' => $meses->pluck('label')->toArray(),
                ],
                'charts' => [
                    'distribucion_estados' => [
                        OfiArchivoExpediente::where('estado', 'Abierto')->count(),
                        OfiArchivoExpediente::where('estado', 'Cerrado')->count(),
                        OfiArchivoExpediente::where('estado', 'Transferido')->count(),
                    ],
                    'prestamos_por_mes' => $meses->pluck('prestamos_activos')->toArray(),
                    'prestamos_devueltos_por_mes' => $meses->pluck('prestamos_devueltos')->toArray(),
                ],
                'ultimos_expedientes' => OfiArchivoExpediente::with(['dependencia'])
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'numero' => $e->numero_expediente,
                        'nombre' => $e->nombre_expediente,
                        'dependencia' => $e->dependencia->nom_organico ?? '—',
                        'estado' => $e->estado,
                        'fecha_apertura' => $e->fecha_apertura?->format('d/m/Y'),
                        'total_folios' => $e->total_folios_elec,
                    ])->toArray(),
                'actividad_reciente' => $this->obtenerActividadReciente(),
            ];
        });
    }

    /**
     * Obtiene las últimas acciones del módulo de archivo para el timeline.
     */
    private function obtenerActividadReciente(): array
    {
        $actividades = [];

        $ultimosPrestamos = OfiArchivoPrestamo::with(['expediente', 'solicitante'])
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($p) => [
                'tipo' => 'prestamo',
                'descripcion' => 'Préstamo de expediente '.$p->expediente->numero_expediente,
                'usuario' => trim(($p->solicitante->nombres ?? '').' '.($p->solicitante->apellidos ?? '')),
                'fecha' => $p->created_at->diffForHumans(),
                'icono' => 'tabler-book',
                'color' => $p->estado === 'prestado' ? 'primary' : 'success',
            ]);

        $ultimasTransferencias = OfiArchivoTransferencia::with(['expediente'])
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($t) => [
                'tipo' => 'transferencia',
                'descripcion' => 'Transferencia de expediente '.$t->expediente->numero_expediente,
                'usuario' => $t->origen,
                'fecha' => $t->created_at->diffForHumans(),
                'icono' => 'tabler-arrows-transfer',
                'color' => $t->estado === 'completada' ? 'success' : 'warning',
            ]);

        return $ultimosPrestamos->concat($ultimasTransferencias)
            ->sortByDesc('fecha')
            ->take(6)
            ->values()
            ->toArray();
    }
}
