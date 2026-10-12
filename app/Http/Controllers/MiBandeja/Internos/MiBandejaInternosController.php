<?php

namespace App\Http\Controllers\MiBandeja\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaInternosController extends Controller
{
    use ApiResponseTrait;

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

            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

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
            $radicados = $query
                ->with(['responsables.userCargo.cargo', 'usuarioCrea.cargoActivo.cargo', 'dependenciaOrigen'])
                ->paginate($perPage);

            // La relación `dependenciaOrigen` se serializa con la misma clave y, vacía, pisaría
            // el valor inferido: se calcula y se descarta la relación antes de responder.
            $radicados->getCollection()->each(fn ($radicado) => $radicado
                ->setAttribute('dependencia_origen', $radicado->dependencia_origen)
                ->unsetRelation('dependenciaOrigen'));
            $this->marcarPuedeFirmar($radicados->getCollection(), $userId);

            return $this->successResponse($radicados, 'Mis radicados internos obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener mis radicados internos', $e->getMessage());
        }
    }

    /**
     * Indica en cada fila si el usuario puede firmarla, con las mismas reglas del flujo de firma:
     * tiene PDF, es firmante designado (FirmaService::esFirmanteDesignado) y, si figura como
     * firmante, aún no ha firmado (M03-19).
     */
    private function marcarPuedeFirmar(Collection $radicados, int $userId): void
    {
        $ids = $radicados->pluck('id');

        $firmados = DB::table('ventanilla_radica_internos_firmantes')
            ->whereIn('radica_interno_id', $ids)
            ->where('users_id', $userId)
            ->pluck('firmado', 'radica_interno_id');

        $responsable = DB::table('ventanilla_radica_internos_responsa')
            ->join('users_cargos', 'users_cargos.id', '=', 'ventanilla_radica_internos_responsa.users_cargos_id')
            ->whereIn('ventanilla_radica_internos_responsa.radica_interno_id', $ids)
            ->where('users_cargos.user_id', $userId)
            ->pluck('ventanilla_radica_internos_responsa.radica_interno_id')
            ->flip();

        $radicados->each(function ($radicado) use ($firmados, $responsable) {
            $designado = $firmados->has($radicado->id) || $responsable->has($radicado->id);

            $radicado->setAttribute('puede_firmar', ! empty($radicado->archivo_digital) && $designado && ! $firmados->get($radicado->id));
        });
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

            return $this->successResponse([
                'total' => $total,
                'borrador' => $borrador,
                'enProceso' => $enProceso,
                'finalizado' => $finalizado,
            ], 'Estadísticas de radicados internos obtenidas exitosamente');
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
