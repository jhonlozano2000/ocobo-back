<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosRespuestas;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            $recibidos = $enviado->recibidos()->get();

            return $this->successResponse($recibidos, 'Radicaados recibidos obtenidos exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
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

            $recibidosAsociados = [];

            foreach ($request->radica_reci_ids as $recibidoId) {
                $existe = VentanillaRadicaEnviadosRespuestas::where('radica_enviado_id', $radicaEnvId)
                    ->where('radica_reci_id', $recibidoId)
                    ->exists();

                if (! $existe) {
                    VentanillaRadicaEnviadosRespuestas::create([
                        'radica_enviado_id' => $radicaEnvId,
                        'radica_reci_id' => $recibidoId,
                    ]);
                    $recibidosAsociados[] = $recibidoId;
                }
            }

            $recibidos = $enviado->recibidos()->get();

            return $this->successResponse($recibidos, 'Radicaados recibidos asociados exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al asociar los radicados recibidos', $e->getMessage(), 500);
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

            $eliminado = VentanillaRadicaEnviadosRespuestas::where('radica_enviado_id', $radicaEnvId)
                ->where('radica_reci_id', $recibidoId)
                ->delete();

            if (! $eliminado) {
                return $this->errorResponse('No se encontró la asociación', null, 404);
            }

            $recibidos = $enviado->recibidos()->get();

            return $this->successResponse($recibidos, 'Radicado recibido desasociado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al desasociar el radicado recibido', $e->getMessage(), 500);
        }
    }
}
