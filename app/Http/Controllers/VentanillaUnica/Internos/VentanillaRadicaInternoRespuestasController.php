<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoRespuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VentanillaRadicaInternoRespuestasController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Interna -> Editar');
    }

    /**
     * Lista los radicados internos respuesta asociados a un radicado interno.
     */
    public function index(Request $request, int $radicaInternoId): JsonResponse
    {
        try {
            $interno = VentanillaRadicaInterno::find($radicaInternoId);

            if (! $interno) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $respuestas = $interno->respuestas()
                ->with(['radicadoInternoRespuesta'])
                ->get();

            $respuestasData = $respuestas->map(function ($respuesta) {
                $internoResp = $respuesta->radicadoInternoRespuesta;
                return [
                    'id' => $respuesta->id,
                    'radicado_interno' => $internoResp ? [
                        'id' => $internoResp->id,
                        'num_radicado' => $internoResp->num_radicado,
                        'asunto' => $internoResp->asunto,
                        'fec_venci' => $internoResp->fec_venci,
                        'estado_trabajo' => $internoResp->estado_trabajo,
                    ] : null,
                ];
            });

            return $this->successResponse($respuestasData, 'Respuestas obtenidas exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener las respuestas', $e->getMessage(), 500);
        }
    }

    /**
     * Lista radicados internos disponibles para asociar como respuesta (tienen fec_venci).
     */
    public function disponibles(Request $request, int $radicaInternoId): JsonResponse
    {
        try {
            $yaAsociados = VentanillaRadicaInternoRespuesta::where('radica_interno_id', $radicaInternoId)
                ->pluck('radica_interno_respuesta_id')
                ->toArray();

            $disponibles = VentanillaRadicaInterno::where('id', '!=', $radicaInternoId)
                ->whereNotNull('fec_venci')
                ->whereNotIn('id', $yaAsociados)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'num_radicado' => $r->num_radicado,
                    'asunto' => $r->asunto,
                    'fec_venci' => $r->fec_venci,
                    'estado_trabajo' => $r->estado_trabajo,
                ]);

            return $this->successResponse($disponibles, 'Radicados internos disponibles');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener disponibles', $e->getMessage(), 500);
        }
    }

    /**
     * Asocia radicados internos como respuesta a un radicado interno.
     */
    public function store(Request $request, int $radicaInternoId): JsonResponse
    {
        try {
            $request->validate([
                'radica_interno_respuesta_ids' => 'required|array|min:1',
                'radica_interno_respuesta_ids.*' => 'integer|exists:ventanilla_radica_internos,id',
            ]);

            $interno = VentanillaRadicaInterno::find($radicaInternoId);

            if (! $interno) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $asociados = [];

            foreach ($request->radica_interno_respuesta_ids as $respuestaId) {
                // Verificar que el radicado interno tenga fec_venci
                $internoResp = VentanillaRadicaInterno::find($respuestaId);
                if (! $internoResp || ! $internoResp->fec_venci) {
                    continue;
                }

                $existe = VentanillaRadicaInternoRespuesta::where('radica_interno_id', $radicaInternoId)
                    ->where('radica_interno_respuesta_id', $respuestaId)
                    ->exists();

                if (! $existe) {
                    VentanillaRadicaInternoRespuesta::create([
                        'radica_interno_id' => $radicaInternoId,
                        'radica_interno_respuesta_id' => $respuestaId,
                    ]);
                    $asociados[] = $respuestaId;
                }
            }

            $respuestas = $interno->respuestas()->with(['radicadoInternoRespuesta'])->get();

            return $this->successResponse($respuestas, 'Respuestas asociadas exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al asociar respuestas', $e->getMessage(), 500);
        }
    }

    /**
     * Desasocia un radicado interno respuesta de un radicado interno.
     */
    public function destroy(Request $request, int $radicaInternoId, int $respuestaId): JsonResponse
    {
        try {
            $interno = VentanillaRadicaInterno::find($radicaInternoId);

            if (! $interno) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $eliminado = VentanillaRadicaInternoRespuesta::where('radica_interno_id', $radicaInternoId)
                ->where('radica_interno_respuesta_id', $respuestaId)
                ->delete();

            if (! $eliminado) {
                return $this->errorResponse('No se encontró la asociación', null, 404);
            }

            $respuestas = $interno->respuestas()->with(['radicadoInternoRespuesta'])->get();

            return $this->successResponse($respuestas, 'Respuesta desasociada exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al desasociar la respuesta', $e->getMessage(), 500);
        }
    }
}
