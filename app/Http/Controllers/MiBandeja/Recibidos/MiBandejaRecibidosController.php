<?php

namespace App\Http\Controllers\MiBandeja\Recibidos;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaRecibidosController extends Controller
{
    /**
     * Obtener radicados asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_reci_responsa.
     */
    public function misRadicados(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estado = $request->get('estado', '');

            $query = VentanillaRadicaReci::query()
                ->whereHas('usuariosResponsables', function ($q) use ($userId) {
                    $q->where('users_cargos.user_id', $userId);
                })
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_radicado', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($estado) {
                $query->where('estado_trabajo', $estado);
            }

            $radicados = $query->with('tercero')->limit(50)->get();

            return response()->json([
                'status' => true,
                'message' => 'Mis radicados obtenidos',
                'data' => $radicados,
            ]);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return response()->json([
                'status' => false,
                'message' => 'Error al obtener mis radicados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estadísticas reales del usuario (no depende del límite de 50).
     */
    public function estadisticas(Request $request)
    {
        try {
            $userId = Auth::id();

            $baseQuery = VentanillaRadicaReci::query()
                ->whereHas('usuariosResponsables', function ($q) use ($userId) {
                    $q->where('users_cargos.user_id', $userId);
                });

            $total = (clone $baseQuery)->count();
            $recibido = (clone $baseQuery)->where('estado_trabajo', 'RECIBIDO')->count();
            $enProceso = (clone $baseQuery)->where('estado_trabajo', 'EN_PROCESO')->count();
            $finalizado = (clone $baseQuery)->where('estado_trabajo', 'FINALIZADO')->count();
            $vencido = (clone $baseQuery)->where('fec_venci', '<', now())
                ->whereNotIn('estado_trabajo', ['FINALIZADO', 'ANULADO'])->count();
            $porVencer = (clone $baseQuery)->whereBetween('fec_venci', [now(), now()->addDays(3)])
                ->whereNotIn('estado_trabajo', ['FINALIZADO', 'ANULADO'])->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'total' => $total,
                    'recibido' => $recibido,
                    'enProceso' => $enProceso,
                    'finalizado' => $finalizado,
                    'vencido' => $vencido,
                    'porVencer' => $porVencer,
                ],
            ]);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return response()->json([
                'status' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
