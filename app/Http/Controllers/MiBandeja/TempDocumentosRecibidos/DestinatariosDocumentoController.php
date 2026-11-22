<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\GuardarDestinatariosDocumentoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Services\MiBandeja\DestinatariosDocumentoService;
use Illuminate\Http\JsonResponse;

class DestinatariosDocumentoController extends Controller
{
    use ApiResponseTrait;

    public function index(Documento $documento, DestinatariosDocumentoService $service): JsonResponse
    {
        return $this->successResponse($service->listar($documento));
    }

    public function update(GuardarDestinatariosDocumentoRequest $request, Documento $documento, DestinatariosDocumentoService $service): JsonResponse
    {
        return $this->successResponse($service->guardar($documento, $request->validated()), 'Destinatarios actualizados.');
    }
}
