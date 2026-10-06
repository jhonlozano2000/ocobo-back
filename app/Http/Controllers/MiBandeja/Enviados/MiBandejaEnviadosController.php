<?php

namespace App\Http\Controllers\MiBandeja\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados as VentanillaRadicaEnviado;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaEnviadosController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtener radicados enviados asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_enviados_responsable.
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

            $query = VentanillaRadicaEnviado::query();

            // Aplicar filtro jerárquico
            if ($nivel === 'ver_todo') {
                $userIds = MiBandejaFiltroService::getUserIdsParaFiltro($userId);

                if ($userIds) {
                    $query->whereHas('usuariosResponsables', function ($q) use ($userIds) {
                        $q->whereIn('users_cargos.user_id', $userIds);
                    });
                } else {
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
            $radicados = $query->paginate($perPage);

            return $this->successResponse($radicados, 'Mis radicados enviados obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener mis radicados enviados', $e->getMessage());
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

            $baseQuery = VentanillaRadicaEnviado::query();

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
            $pendiente = (clone $baseQuery)->where('estado_trabajo', 'PENDIENTE')->count();
            $enProceso = (clone $baseQuery)->where('estado_trabajo', 'EN_PROCESO')->count();
            $finalizado = (clone $baseQuery)->where('estado_trabajo', 'FINALIZADO')->count();

            return $this->successResponse([
                'total' => $total,
                'pendiente' => $pendiente,
                'enProceso' => $enProceso,
                'finalizado' => $finalizado,
            ], 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estadísticas', $e->getMessage());
        }
    }
}
