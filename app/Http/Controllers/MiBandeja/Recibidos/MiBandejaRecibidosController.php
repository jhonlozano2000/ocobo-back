<?php

namespace App\Http\Controllers\MiBandeja\Recibidos;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaRecibidosController extends Controller
{
    /**
     * Obtener radicados asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_reci_responsa.
     *
     * Parámetros:
     * - nivel: 'mis_radicados' | 'ver_todo' (default: 'mis_radicados')
     */
    public function misRadicados(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estado = $request->get('estado', '');
            $nivel = $request->get('nivel', 'mis_radicados');

            $query = VentanillaRadicaReci::query();

            // Aplicar filtro jerárquico
            if ($nivel === 'ver_todo') {
                $userIds = MiBandejaFiltroService::getUserIdsParaFiltro($userId);

                if ($userIds) {
                    $query->whereHas('usuariosResponsables', function ($q) use ($userIds) {
                        $q->whereIn('users_cargos.user_id', $userIds);
                    });
                } else {
                    // Sin permisos de jefe, fallback a mis radicados
                    $query->whereHas('usuariosResponsables', function ($q) use ($userId) {
                        $q->where('users_cargos.user_id', $userId);
                    });
                }
            } else {
                $query->whereHas('usuariosResponsables', function ($q) use ($userId) {
                    $q->where('users_cargos.user_id', $userId);
                });
            }

            $query->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_radicado', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($estado) {
                $query->where('estado_trabajo', $estado);
            }

            $perPage = min((int) $request->get('per_page', 15), 100);
            $radicados = $query->with('tercero')->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Mis radicados obtenidos',
                'data' => $radicados->items(),
                'pagination' => [
                    'total' => $radicados->total(),
                    'per_page' => $radicados->perPage(),
                    'current_page' => $radicados->currentPage(),
                    'last_page' => $radicados->lastPage(),
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
                'message' => 'Error al obtener mis radicados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estadísticas reales del usuario (no depende del límite de 50).
     *
     * Parámetros:
     * - nivel: 'mis_radicados' | 'ver_todo' (default: 'mis_radicados')
     */
    public function estadisticas(Request $request)
    {
        try {
            $userId = Auth::id();
            $nivel = $request->get('nivel', 'mis_radicados');

            $baseQuery = VentanillaRadicaReci::query();

            // Aplicar filtro jerárquico
            if ($nivel === 'ver_todo') {
                $userIds = MiBandejaFiltroService::getUserIdsParaFiltro($userId);

                if ($userIds) {
                    $baseQuery->whereHas('usuariosResponsables', function ($q) use ($userIds) {
                        $q->whereIn('users_cargos.user_id', $userIds);
                    });
                } else {
                    $baseQuery->whereHas('usuariosResponsables', function ($q) use ($userId) {
                        $q->where('users_cargos.user_id', $userId);
                    });
                }
            } else {
                $baseQuery->whereHas('usuariosResponsables', function ($q) use ($userId) {
                    $q->where('users_cargos.user_id', $userId);
                });
            }

            $total = (clone $baseQuery)->count();
            $recibido = (clone $baseQuery)->where('estado_trabajo', 'RECIBIDO')->count();
            $enProceso = (clone $baseQuery)->where('estado_trabajo', 'EN_PROCESO')->count();
            $finalizado = (clone $baseQuery)->where('estado_trabajo', 'FINALIZADO')->count();
            $vencido = (clone $baseQuery)->where('fec_venci', '<', now())
                ->whereNotIn('estado_trabajo', ['FINALIZADO', 'ANULADO'])->count();
            $porVencer = (clone $baseQuery)->whereBetween('fec_venci', [now(), now()->addDays(3)])
                ->whereNotIn('estado_trabajo', ['FINALIZADO', 'ANULADO'])->count();
            $sinArchivoDigital = (clone $baseQuery)->whereNull('archivo_digital')
                ->where('estado_trabajo', '!=', 'ANULADO')->count();
            $porFirmar = (clone $baseQuery)->where('estado_firma', '!=', 'firmado')
                ->whereNotNull('estado_firma')
                ->where('estado_trabajo', '!=', 'ANULADO')->count();
            $vigentes = $total - $vencido - $porVencer - $finalizado;

            return response()->json([
                'status' => true,
                'data' => [
                    'total' => $total,
                    'recibido' => $recibido,
                    'enProceso' => $enProceso,
                    'finalizado' => $finalizado,
                    'vencido' => $vencido,
                    'porVencer' => $porVencer,
                    'vigentes' => $vigentes,
                    'sinArchivoDigital' => $sinArchivoDigital,
                    'porFirmar' => $porFirmar,
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
