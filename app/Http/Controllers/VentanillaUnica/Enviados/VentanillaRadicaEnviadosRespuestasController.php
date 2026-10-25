<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosRespuestas;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VentanillaRadicaEnviadosRespuestasController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Enviada -> Editar');
    }

    /**
     * Lista los radicados recibidos asociados a un radicado enviado.
     */
    public function index(Request $request, int $radicaEnvId): JsonResponse
    {
        try {
            $enviado = VentanillaRadicaEnviados::find($radicaEnvId);

            if (! $enviado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            return $this->successResponse($this->listado($enviado), 'Radicados recibidos obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener los radicados recibidos', $e->getMessage(), 500);
        }
    }

    /**
     * Asocia radicados recibidos a un radicado enviado.
     */
    public function store(Request $request, int $radicaEnvId): JsonResponse
    {
        try {
            $request->validate([
                'radica_reci_ids' => 'required|array|min:1',
                'radica_reci_ids.*' => 'integer|exists:ventanilla_radica_reci,id',
            ]);

            $enviado = VentanillaRadicaEnviados::find($radicaEnvId);

            if (! $enviado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            // Asociar marca el recibido como tramitado: solo los que el usuario puede ver
            $sinAcceso = collect($request->radica_reci_ids)
                ->reject(fn ($recibidoId) => VentanillaRadicaReci::accesiblePara($request->user(), (int) $recibidoId))
                ->values();
            if ($sinAcceso->isNotEmpty()) {
                return $this->errorResponse('No tiene acceso a uno o más radicados recibidos', ['radica_reci_ids' => $sinAcceso], 403);
            }

            DB::transaction(fn () => $this->asociar($enviado, $request->radica_reci_ids));

            return $this->successResponse($this->listado($enviado), 'Radicados recibidos asociados exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al asociar los radicados recibidos', $e->getMessage(), 500);
        }
    }

    /** @param  list<int>  $recibidoIds */
    private function asociar(VentanillaRadicaEnviados $enviado, array $recibidoIds): void
    {
        $radicaEnvId = $enviado->id;

        foreach ($recibidoIds as $recibidoId) {
            $existe = VentanillaRadicaEnviadosRespuestas::where('radica_enviado_id', $radicaEnvId)
                ->where('radica_reci_id', $recibidoId)
                ->exists();

            if (! $existe) {
                VentanillaRadicaEnviadosRespuestas::create([
                    'radica_enviado_id' => $radicaEnvId,
                    'radica_reci_id' => $recibidoId,
                ]);

                $radRecibido = VentanillaRadicaReci::find($recibidoId);
                if ($radRecibido) {
                    $radRecibido->update([
                        'radicado_respuesta' => $enviado->num_radicado,
                        'estado_trabajo' => RadicadoEstadoTrabajoService::ESTADO_TRAMITADO,
                    ]);
                }
            }
        }

    }

    /**
     * Desasocia un radicado recibido de un radicado enviado.
     */
    public function destroy(Request $request, int $radicaEnvId, int $recibidoId): JsonResponse
    {
        try {
            $enviado = VentanillaRadicaEnviados::find($radicaEnvId);

            if (! $enviado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $eliminado = DB::transaction(function () use ($enviado, $recibidoId) {
                $eliminado = VentanillaRadicaEnviadosRespuestas::where('radica_enviado_id', $enviado->id)
                    ->where('radica_reci_id', $recibidoId)
                    ->delete();
                if ($eliminado) {
                    $this->revertirRespuesta($enviado, $recibidoId);
                }

                return $eliminado;
            });

            if (! $eliminado) {
                return $this->errorResponse('No se encontró la asociación', null, 404);
            }

            return $this->successResponse($this->listado($enviado), 'Radicado recibido desasociado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al desasociar el radicado recibido', $e->getMessage(), 500);
        }
    }

    /**
     * Lo que muestra la pestaña de respuestas del enviado: lo justo para identificar cada recibido.
     *
     * @return list<array<string, mixed>>
     */
    private function listado(VentanillaRadicaEnviados $enviado): array
    {
        return $enviado->recibidos()
            ->orderBy('ventanilla_radica_reci.created_at')
            ->get(['ventanilla_radica_reci.id', 'num_radicado', 'asunto', 'estado_trabajo', 'ventanilla_radica_reci.created_at'])
            ->map(fn (VentanillaRadicaReci $recibido) => [
                'id' => $recibido->id,
                'num_radicado' => $recibido->num_radicado,
                'asunto' => $recibido->asunto,
                'estado_trabajo' => $recibido->estado_trabajo,
                'created_at' => $recibido->created_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * Al quitar la asociación el recibido deja de figurar como respondido por este enviado: toma la
     * respuesta de otro enviado asociado o, si no queda ninguno, sale de TRAMITADO.
     */
    private function revertirRespuesta(VentanillaRadicaEnviados $enviado, int $recibidoId): void
    {
        $recibido = VentanillaRadicaReci::find($recibidoId);
        if (! $recibido || $recibido->radicado_respuesta !== $enviado->num_radicado) {
            return;
        }

        $otraRespuesta = VentanillaRadicaEnviados::whereIn(
            'id',
            VentanillaRadicaEnviadosRespuestas::where('radica_reci_id', $recibidoId)->select('radica_enviado_id')
        )->latest('id')->value('num_radicado');

        if ($otraRespuesta) {
            $recibido->update(['radicado_respuesta' => $otraRespuesta]);

            return;
        }

        $recibido->radicado_respuesta = null;
        if ($recibido->estado_trabajo === RadicadoEstadoTrabajoService::ESTADO_TRAMITADO) {
            $recibido->estado_trabajo = app(RadicadoEstadoTrabajoService::class)->calcularEstadoAutomatico($recibido);
        }
        $recibido->save();
    }
}
