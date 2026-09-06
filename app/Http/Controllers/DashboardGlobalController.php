<?php

namespace App\Http\Controllers;

use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\ControlAcceso\UserService;
use App\Services\OfiArchivo\ReportesService as ArchivoReportesService;
use App\Services\VentanillaUnica\PqrsService;
use App\Services\VentanillaUnica\RadicacionEnviadosService;
use App\Services\VentanillaUnica\RadicacionReciService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DashboardGlobalController extends Controller
{
    public function __construct(
        protected ArchivoReportesService $archivoReportes,
        protected PqrsService $pqrsService,
        protected RadicacionReciService $reciService,
        protected RadicacionEnviadosService $enviadosService,
        protected UserService $userService,
    ) {}

    public function index(): JsonResponse
    {
        $data = Cache::remember('dashboard_global_stats', 300, function () {
            return $this->aggregateAll();
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    protected function aggregateAll(): array
    {
        $archivo = $this->archivoReportes->estadisticasGenerales();
        $recibidos = rescue(fn () => $this->reciService->getStats(), ['total_radicados' => 0, 'pendientes' => 0, 'vencidos' => 0]);
        $enviados = rescue(fn () => $this->enviadosService->getStats(), ['total_enviados' => 0, 'total_pendientes' => 0]);
        $pqrs = rescue(fn () => $this->pqrsService->getEstadisticas(), ['total' => 0, 'pendientes' => 0, 'urgentes' => 0, 'vencidas' => 0]);
        $usuarios = rescue(fn () => $this->userService->getStats(), ['total_users' => 0, 'total_users_activos' => 0, 'total_users_inactivos' => 0]);

        return [
            'radicados_recibidos' => [
                'total' => $recibidos['total_radicados'],
                'hoy' => VentanillaRadicaReci::whereDate('created_at', today())->count(),
                'mes' => VentanillaRadicaReci::where('created_at', '>=', now()->startOfMonth())->count(),
                'por_estado' => [
                    'Pendiente' => $recibidos['pendientes'],
                    'Vencido' => $recibidos['vencidos'],
                ],
            ],
            'radicados_enviados' => [
                'total' => $enviados['total_enviados'],
                'hoy' => VentanillaRadicaEnviados::whereDate('created_at', today())->count(),
                'mes' => VentanillaRadicaEnviados::where('created_at', '>=', now()->startOfMonth())->count(),
                'por_estado' => [
                    'Pendiente' => $enviados['total_pendientes'],
                ],
            ],
            'pqrs' => $pqrs,
            'archivo' => [
                'expedientes' => $archivo['expedientes'] ?? ['total' => 0, 'abiertos' => 0, 'cerrados' => 0],
                'prestamos' => $archivo['prestamos'] ?? ['activos' => 0, 'vencidos' => 0],
                'transferencias' => $archivo['transferencias'] ?? ['pendientes' => 0, 'completadas_hoy' => 0],
                'sparklines' => $archivo['sparklines'] ?? [],
            ],
            'usuarios' => [
                'total_users' => $usuarios['total_users'],
                'activos' => $usuarios['total_users_activos'],
                'inactivos' => $usuarios['total_users_inactivos'],
            ],
            'firmas' => $this->getFirmasStats(),
            'vencimientos_proximos' => $this->getVencimientosProximos(),
        ];
    }

    protected function getFirmasStats(): array
    {
        return [
            'hoy' => FirmaEvento::whereDate('created_at', today())->count(),
            'semana' => FirmaEvento::where('created_at', '>=', now()->subWeek())->count(),
            'mes' => FirmaEvento::where('created_at', '>=', now()->subMonth())->count(),
        ];
    }

    protected function getVencimientosProximos(): array
    {
        $limite = now()->addDays(7);
        $items = [];

        $recibidos = VentanillaRadicaReci::where('fec_venci', '<=', $limite)
            ->where('fec_venci', '>=', now())
            ->where('estado_trabajo', '!=', 'FINALIZADO')
            ->get(['id', 'num_radicado', 'asunto', 'fec_venci']);

        foreach ($recibidos as $r) {
            $items[] = [
                'tipo' => 'Radicado Recibido',
                'referencia' => $r->num_radicado,
                'descripcion' => Str::limit($r->asunto, 80),
                'fecha_vencimiento' => $r->fec_venci?->format('Y-m-d'),
                'dias_restantes' => now()->diffInDays($r->fec_venci, false),
            ];
        }

        $pqrs = VentanillaPqrs::with('radicado')
            ->where('fecha_vencimiento', '<=', $limite)
            ->where('fecha_vencimiento', '>=', now())
            ->whereNotIn('estado_tramite', ['Respondida', 'Cerrado'])
            ->get(['id', 'ventanilla_radica_reci_id', 'detalle_solicitud', 'fecha_vencimiento']);

        foreach ($pqrs as $p) {
            $items[] = [
                'tipo' => 'PQRS',
                'referencia' => $p->radicado?->num_radicado ?? "PQRS-{$p->id}",
                'descripcion' => Str::limit($p->detalle_solicitud, 80),
                'fecha_vencimiento' => $p->fecha_vencimiento?->format('Y-m-d'),
                'dias_restantes' => now()->diffInDays($p->fecha_vencimiento, false),
            ];
        }

        $prestamos = OfiArchivoPrestamo::where('fecha_devolucion_esperada', '<', $limite)
            ->where('estado', 'Activo')
            ->get(['id', 'solicitante_id', 'fecha_prestamo', 'fecha_devolucion_esperada']);

        foreach ($prestamos as $pr) {
            $items[] = [
                'tipo' => 'Préstamo Vencido',
                'referencia' => "P-{$pr->id}",
                'descripcion' => "Préstamo #{$pr->id}",
                'fecha_vencimiento' => $pr->fecha_devolucion_esperada?->format('Y-m-d'),
                'dias_restantes' => now()->diffInDays($pr->fecha_devolucion_esperada, false),
            ];
        }

        usort($items, fn ($a, $b) => ($a['dias_restantes'] ?? 999) <=> ($b['dias_restantes'] ?? 999));

        return $items;
    }

    /**
     * Sparkline data: radicados recibidos por día en los últimos 30 días.
     */
    public function sparklines(): JsonResponse
    {
        $dias = 30;
        $inicio = now()->subDays($dias);

        $recibidosPorDia = VentanillaRadicaReci::where('created_at', '>=', $inicio)
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        $enviadosPorDia = VentanillaRadicaEnviados::where('created_at', '>=', $inicio)
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        $pqrsPorDia = VentanillaPqrs::where('created_at', '>=', $inicio)
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        $usuariosTotales = User::count();
        $usuariosActivos = User::where('estado', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'radicados_recibidos' => $this->formatSparkline($recibidosPorDia, $dias),
                'radicados_enviados' => $this->formatSparkline($enviadosPorDia, $dias),
                'pqrs' => $this->formatSparkline($pqrsPorDia, $dias),
                'usuarios' => [
                    'total' => $usuariosTotales,
                    'activos' => $usuariosActivos,
                ],
                'firmas' => [
                    'hoy' => FirmaEvento::whereDate('created_at', today())->count(),
                    'semana' => FirmaEvento::where('created_at', '>=', now()->subWeek())->count(),
                    'mes' => FirmaEvento::where('created_at', '>=', now()->subMonth())->count(),
                ],
            ],
        ]);
    }

    private function formatSparkline($data, int $dias): array
    {
        $result = [];
        for ($i = $dias; $i >= 0; $i--) {
            $fecha = now()->subDays($i)->format('Y-m-d');
            $result[] = [
                'fecha' => $fecha,
                'total' => $data[$fecha] ?? 0,
            ];
        }

        return $result;
    }
}
