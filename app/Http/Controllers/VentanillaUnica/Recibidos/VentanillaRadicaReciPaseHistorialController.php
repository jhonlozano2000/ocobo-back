<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Recibidos\StorePaseHistorialRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciPaseHistorial;
use App\Services\VentanillaUnica\Recibidos\PaseHistorialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaReciPaseHistorialController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['store']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['byRadicado']);
    }

    /**
     * Registra un nuevo pase sobre un radicado.
     */
    public function store($radica_reci_id, StorePaseHistorialRequest $request, PaseHistorialService $service)
    {
        try {
            $data = $request->validated();
            $data['radica_reci_id'] = $radica_reci_id;

            $result = $service->registrarPase($data);

            return $this->successResponse($result, 'Pase registrado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al registrar el pase', $e->getMessage(), 500);
        }
    }

    /**
     * Listar historial de pases de un radicado.
     */
    public function byRadicado($radica_reci_id)
    {
        try {
            $historial = VentanillaRadicaReciPaseHistorial::with([
                'usuarioOrigen',
                'usuarioDestino',
                'usersCargosDestino.cargo'
            ])
                ->where('radica_reci_id', $radica_reci_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de pases obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de pases', $e->getMessage(), 500);
        }
    }
}