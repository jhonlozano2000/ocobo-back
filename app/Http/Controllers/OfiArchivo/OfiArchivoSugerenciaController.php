<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\OfiArchivo\SugerenciaExpedienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * B09 (diseño de expedientes §6.1): panel "Expediente" del radicado recibido, el enviado y la PQRS.
 */
class OfiArchivoSugerenciaController extends Controller
{
    use ApiResponseTrait;

    public function __invoke(Request $request, SugerenciaExpedienteService $service): JsonResponse
    {
        $datos = $request->validate([
            'origen_tipo' => 'required|in:radicado_recibido,pqrs,radicado_enviado',
            'origen_id' => 'required|integer|min:1',
        ], [
            'origen_tipo.in' => 'Solo se sugieren expedientes para radicados recibidos, enviados o PQRS.',
        ]);

        $sugerencia = $service->para($datos['origen_tipo'], (int) $datos['origen_id'], $request->user());

        return $sugerencia
            ? $this->successResponse($sugerencia, 'Sugerencia de expediente')
            : $this->errorResponse('Radicado no encontrado', null, 404);
    }
}
