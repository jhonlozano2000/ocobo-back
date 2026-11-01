<?php

namespace App\Http\Controllers\MiBandeja\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados as VentanillaRadicaEnviado;
use App\Services\MiBandeja\MiBandejaFiltroService;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaEnviadosController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtener radicados enviados asignados al usuario actual (Mi Bandeja).
     * Incluye los radicados donde el usuario es responsable o firmante.
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

            $this->filtrarAsignados($query, $userId, $nivel);

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

            $this->filtrarAsignados($baseQuery, $userId, $nivel);

            $total = (clone $baseQuery)->count();
            // Estados que asigna calcularEstadoTrabajo(); PENDIENTE no existe y FINALIZADO casi nunca llega
            $pendiente = RadicadoEstadoTrabajoService::filtrarPendientes(clone $baseQuery)->count();
            $enProceso = (clone $baseQuery)->where('estado_trabajo', 'EN_PROCESO')->count();
            $finalizado = (clone $baseQuery)->whereIn('estado_trabajo', RadicadoEstadoTrabajoService::ESTADOS_TERMINADOS)->count();

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

    /**
     * Radicados asignados al usuario como responsable o firmante; con 'ver_todo', los de su jerarquía.
     */
    private function filtrarAsignados(Builder $query, int $userId, string $nivel): void
    {
        $userIds = ($nivel === 'ver_todo' ? MiBandejaFiltroService::getUserIdsParaFiltro($userId) : null) ?: [$userId];

        $query->where(fn ($q) => $q
            ->whereHas('usuariosResponsables', fn ($r) => $r->whereIn('users_cargos.user_id', $userIds))
            ->orWhereHas('usuariosFirmas', fn ($f) => $f->whereIn('users_cargos.user_id', $userIds)));
    }
}
