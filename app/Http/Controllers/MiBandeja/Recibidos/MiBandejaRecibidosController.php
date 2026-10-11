<?php

namespace App\Http\Controllers\MiBandeja\Recibidos;

use App\Helpers\CalendarioHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaRecibidosController extends Controller
{
    use ApiResponseTrait;

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
                        ->orWhere('asunto', 'like', "%{$search}%")
                        ->orWhereHas('tercero', function ($tq) use ($search) {
                            $tq->where('nom_razo_soci', 'like', "%{$search}%")
                                ->orWhere('num_docu_nit', 'like', "%{$search}%");
                        })
                        ->orWhereHas('usuariosResponsables.user', function ($uq) use ($search) {
                            $uq->where('nombres', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%");
                        });
                });
            }

            if ($estado) {
                $query->where('estado_trabajo', $estado);
            }

            $perPage = min((int) $request->get('per_page', 15), 100);
            $radicados = $query->with([
                'tercero',
                'clasificacionDocumental',
                'medioRecepcion',
                'usuariosResponsables.user',
                'usuariosResponsables.cargo',
                'compartirHistorial',
            ])->paginate($perPage);

            return $this->successResponse($radicados, 'Mis radicados obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener mis radicados', $e->getMessage());
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
            $vencido = (clone $baseQuery)->where('fec_venci', '<', CalendarioHelper::hoy()->toDateString())
                ->whereNotIn('estado_trabajo', ['FINALIZADO', 'ANULADO'])->count();
            $porVencer = (clone $baseQuery)->whereBetween('fec_venci', [CalendarioHelper::hoy()->toDateString(), CalendarioHelper::hoy()->addDays(5)->toDateString()])
                ->whereNotIn('estado_trabajo', ['FINALIZADO', 'ANULADO'])->count();
            $sinArchivoDigital = (clone $baseQuery)->whereNull('archivo_digital')
                ->where('estado_trabajo', '!=', 'ANULADO')->count();
            $porFirmar = (clone $baseQuery)->where('estado_firma', '!=', 'firmado')
                ->whereNotNull('estado_firma')
                ->where('estado_trabajo', '!=', 'ANULADO')->count();
            $vigentes = $total - $vencido - $porVencer - $finalizado;

            return $this->successResponse([
                'total' => $total,
                'recibido' => $recibido,
                'enProceso' => $enProceso,
                'finalizado' => $finalizado,
                'vencido' => $vencido,
                'porVencer' => $porVencer,
                'vigentes' => $vigentes,
                'sinArchivoDigital' => $sinArchivoDigital,
                'porFirmar' => $porFirmar,
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
