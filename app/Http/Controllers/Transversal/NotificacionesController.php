<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de notificaciones y alertas del sistema.
 *
 * Expone el conteo de alertas de vencimiento para el badge del frontend.
 * Normativa: Ley 1437/2011 (CPACA), Acuerdo 060/2001 AGN.
 */
class NotificacionesController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Retorna el conteo de documentos próximos a vencer o ya vencidos
     * asignados al usuario autenticado.
     */
    public function pendientes(): JsonResponse
    {
        $user = Auth::user();

        // Buscar cargo(s) activo(s) del usuario
        $userCargoIds = $user->cargos()->pluck('users_cargos.id');

        $hoy = now()->toDateString();
        $en3dias = now()->addDays(3)->toDateString();

        // ── Recibidos ─────────────────────────────────────────────
        $recibidosVencidos = VentanillaRadicaReci::whereHas('responsables', fn ($q) => $q->whereIn('users_cargos_id', $userCargoIds))
            ->whereIn('estado_trabajo', ['Pendiente', 'En Proceso'])
            ->whereDate('fec_venci', '<', $hoy)
            ->count();

        $recibidosProximos = VentanillaRadicaReci::whereHas('responsables', fn ($q) => $q->whereIn('users_cargos_id', $userCargoIds))
            ->whereIn('estado_trabajo', ['Pendiente', 'En Proceso'])
            ->whereDate('fec_venci', '>=', $hoy)
            ->whereDate('fec_venci', '<=', $en3dias)
            ->count();

        // ── Enviados ──────────────────────────────────────────────
        $enviadosVencidos = VentanillaRadicaEnviados::whereHas('responsables', fn ($q) => $q->whereIn('users_cargos_id', $userCargoIds))
            ->whereIn('estado_trabajo', ['Pendiente', 'En Proceso'])
            ->whereNotNull('fec_venci')
            ->whereDate('fec_venci', '<', $hoy)
            ->count();

        $enviadosProximos = VentanillaRadicaEnviados::whereHas('responsables', fn ($q) => $q->whereIn('users_cargos_id', $userCargoIds))
            ->whereIn('estado_trabajo', ['Pendiente', 'En Proceso'])
            ->whereNotNull('fec_venci')
            ->whereDate('fec_venci', '>=', $hoy)
            ->whereDate('fec_venci', '<=', $en3dias)
            ->count();

        // ── PQRS ──────────────────────────────────────────────────
        $pqrsVencidas = VentanillaPqrs::whereHas('radicado.responsables', fn ($q) => $q->whereIn('users_cargos_id', $userCargoIds))
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->count();

        $pqrsProximas = VentanillaPqrs::whereHas('radicado.responsables', fn ($q) => $q->whereIn('users_cargos_id', $userCargoIds))
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->whereDate('fecha_vencimiento', '<=', $en3dias)
            ->count();

        $totalVencidos = $recibidosVencidos + $enviadosVencidos + $pqrsVencidas;
        $totalProximos = $recibidosProximos + $enviadosProximos + $pqrsProximas;

        return $this->successResponse([
            'total_alertas' => $totalVencidos + $totalProximos,
            'vencidos' => [
                'total' => $totalVencidos,
                'recibidos' => $recibidosVencidos,
                'enviados' => $enviadosVencidos,
                'pqrs' => $pqrsVencidas,
            ],
            'proximos_3_dias' => [
                'total' => $totalProximos,
                'recibidos' => $recibidosProximos,
                'enviados' => $enviadosProximos,
                'pqrs' => $pqrsProximas,
            ],
        ], 'Alertas de vencimiento');
    }
}
