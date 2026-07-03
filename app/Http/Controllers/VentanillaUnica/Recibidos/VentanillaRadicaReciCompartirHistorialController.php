<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Recibidos\StoreCompartirHistorialRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciCompartirHistorial;
use App\Services\VentanillaUnica\Recibidos\CompartirHistorialService;
use Illuminate\Http\JsonResponse;

class VentanillaRadicaReciCompartirHistorialController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['store']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['byRadicado']);
    }

    /**
     * Registra un nuevo compartir (con copia / CC) sobre un radicado.
     */
    public function store($radica_reci_id, StoreCompartirHistorialRequest $request, CompartirHistorialService $service)
    {
        try {
            $data = $request->validated();
            $data['radica_reci_id'] = $radica_reci_id;

            $result = $service->registrarCompartir($data);

            return $this->successResponse($result, 'Radicado compartido exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al compartir el radicado', $e->getMessage(), 500);
        }
    }

    /**
     * Listar historial de compartir (CC) de un radicado.
     */
    public function byRadicado($radica_reci_id)
    {
        try {
            $historial = VentanillaRadicaReciCompartirHistorial::with([
                'usuarioOrigen',
                'usuarioDestino',
                'usersCargosDestino.cargo'
            ])
                ->where('radica_reci_id', $radica_reci_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de compartir obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de compartir', $e->getMessage(), 500);
        }
    }
}