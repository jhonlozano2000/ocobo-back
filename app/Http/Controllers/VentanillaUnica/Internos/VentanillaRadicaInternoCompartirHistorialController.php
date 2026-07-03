<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Internos\StoreCompartirHistorialInternoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoCompartirHistorial;
use App\Services\VentanillaUnica\Internos\CompartirHistorialInternoService;
use Illuminate\Http\JsonResponse;

class VentanillaRadicaInternoCompartirHistorialController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Interna -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['store']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['byRadicado']);
    }

    /**
     * Registra un nuevo compartir (con copia / CC) sobre un radicado interno.
     */
    public function store($radica_interno_id, StoreCompartirHistorialInternoRequest $request, CompartirHistorialInternoService $service): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['radica_interno_id'] = $radica_interno_id;

            $result = $service->registrarCompartir($data);

            return $this->successResponse($result, 'Radicado compartido exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al compartir el radicado', $e->getMessage(), 500);
        }
    }

    /**
     * Listar historial de compartir (CC) de un radicado interno.
     */
    public function byRadicado($radica_interno_id): JsonResponse
    {
        try {
            $historial = VentanillaRadicaInternoCompartirHistorial::with([
                'usuarioOrigen',
                'usuarioDestino',
                'usersCargosDestino.cargo'
            ])
                ->where('radica_interno_id', $radica_interno_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de compartir obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de compartir', $e->getMessage(), 500);
        }
    }
}
