<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Recibidos\StorePaseHistorialRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciPaseHistorial;
use App\Services\VentanillaUnica\Recibidos\PaseHistorialService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VentanillaRadicaReciPaseHistorialController extends Controller
{
    use ApiResponseTrait;

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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse($e->getMessage(), null, 422);
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
                'usersCargosDestino.cargo',
            ])
                ->where('radica_reci_id', $radica_reci_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de pases obtenido exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener el historial de pases', $e->getMessage(), 500);
        }
    }
}
