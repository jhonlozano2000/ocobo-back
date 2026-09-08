<?php

namespace App\Http\Controllers\MiBandeja\Internos;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaInternosController extends Controller
{
    /**
     * Obtener radicados internos asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_internos_responsa.
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

            $query = VentanillaRadicaInterno::query();

            // Aplicar filtro jerárquico
            if ($nivel === 'ver_todo') {
                $userIds = MiBandejaFiltroService::getUserIdsParaFiltro($userId);

                if ($userIds) {
                    $query->whereHas('responsables', function ($q) use ($userIds) {
                        $q->whereHas('userCargo', function ($q2) use ($userIds) {
                            $q2->whereIn('user_id', $userIds);
                        });
                    });
                } else {
                    $query->whereHas('responsables', function ($q) use ($userId) {
                        $q->whereHas('userCargo', function ($q2) use ($userId) {
                            $q2->where('user_id', $userId);
                        });
                    });
                }
            } else {
                $query->whereHas('responsables', function ($q) use ($userId) {
                    $q->whereHas('userCargo', function ($q2) use ($userId) {
                        $q2->where('user_id', $userId);
                    });
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
     *
     * Parámetros:
     * - nivel: 'mis_radicados' | 'ver_todo' (default: 'mis_radicados')
     */
    public function estadisticas(Request $request)
    {
        try {
            $userId = Auth::id();
            $nivel = $request->get('nivel', 'mis_radicados');

            $baseQuery = VentanillaRadicaInterno::query();

            // Aplicar filtro jerárquico
            if ($nivel === 'ver_todo') {
                $userIds = MiBandejaFiltroService::getUserIdsParaFiltro($userId);

                if ($userIds) {
                    $baseQuery->whereHas('responsables', function ($q) use ($userIds) {
                        $q->whereHas('userCargo', function ($q2) use ($userIds) {
                            $q2->whereIn('user_id', $userIds);
                        });
                    });
                } else {
                    $baseQuery->whereHas('responsables', function ($q) use ($userId) {
                        $q->whereHas('userCargo', function ($q2) use ($userId) {
                            $q2->where('user_id', $userId);
                        });
                    });
                }
            } else {
                $baseQuery->whereHas('responsables', function ($q) use ($userId) {
                    $q->whereHas('userCargo', function ($q2) use ($userId) {
                        $q2->where('user_id', $userId);
                    });
                });
            }

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
