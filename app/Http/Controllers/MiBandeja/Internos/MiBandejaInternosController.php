<?php

namespace App\Http\Controllers\MiBandeja\Internos;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaInternosController extends Controller
{
    /**
     * Obtener radicados internos asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_internos_responsa.
     */
    public function misRadicados(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estado = $request->get('estado', '');

            $query = VentanillaRadicaInterno::query()
                ->whereHas('responsables', function ($q) use ($userId) {
                    $q->whereHas('userCargo', function ($q2) use ($userId) {
                        $q2->where('user_id', $userId);
                    });
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

            $radicados = $query
                ->with(['responsables.userCargo', 'usuarioCrea.cargoActivo.cargo', 'dependenciaOrigen'])
                ->limit(50)
                ->get()
                ->each->append('dependencia_origen');

            return response()->json([
                'status' => true,
                'message' => 'Mis radicados internos obtenidos',
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
                'message' => 'Error al obtener mis radicados internos',
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

            $baseQuery = VentanillaRadicaInterno::query()
                ->whereHas('responsables', function ($q) use ($userId) {
                    $q->whereHas('userCargo', function ($q2) use ($userId) {
                        $q2->where('user_id', $userId);
                    });
                });

            $total = (clone $baseQuery)->count();
            $borrador = (clone $baseQuery)->where('estado_trabajo', 'BORRADOR')->count();
            $enProceso = (clone $baseQuery)->where('estado_trabajo', 'EN_PROCESO')->count();
            $finalizado = (clone $baseQuery)->where('estado_trabajo', 'FINALIZADO')->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'total' => $total,
                    'borrador' => $borrador,
                    'enProceso' => $enProceso,
                    'finalizado' => $finalizado,
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
