<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Internos\StorePaseHistorialInternoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoPaseHistorial;
use App\Services\VentanillaUnica\Internos\PaseHistorialInternoService;
use Illuminate\Http\JsonResponse;

class VentanillaRadicaInternoPaseHistorialController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Interna -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['store']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['byRadicado']);
    }

    /**
     * Registra un nuevo pase sobre un radicado interno.
     */
    public function store($radica_interno_id, StorePaseHistorialInternoRequest $request, PaseHistorialInternoService $service): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['radica_interno_id'] = $radica_interno_id;

            $result = $service->registrarPase($data);

            return $this->successResponse($result, 'Pase registrado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al registrar el pase', $e->getMessage(), 500);
        }
    }

    /**
     * Listar historial de pases de un radicado interno.
     */
    public function byRadicado($radica_interno_id): JsonResponse
    {
        try {
            $historial = VentanillaRadicaInternoPaseHistorial::with([
                'usuarioOrigen',
                'usuarioDestino',
                'usersCargosDestino.cargo'
            ])
                ->where('radica_interno_id', $radica_interno_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de pases obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de pases', $e->getMessage(), 500);
        }
    }
}
