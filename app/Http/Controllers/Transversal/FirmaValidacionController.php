<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transversal\FirmaValidarRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Transversal\FirmaEvento;
use App\Services\Firma\FirmaValidacionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FirmaValidacionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaValidacionService $validacionService
    ) {}

    public function validar(FirmaValidarRequest $request)
    {
        $modelClass = FirmaValidacionService::MODEL_MAP[$request->input('documentable_type')];

        // M06-12: la validación devuelve el firmante y su correo; mismo alcance que el historial de firmas
        if (! FirmaEvento::usuarioPuedeConsultar($modelClass, (int) $request->input('documentable_id'), $request->user())) {
            return $this->errorResponse('No tiene permisos para validar este documento', null, 403);
        }

        try {
            $resultado = $this->validacionService->validar(
                $request->input('documentable_type'),
                $request->input('documentable_id')
            );

            return $this->successResponse($resultado,
                $resultado['valido']
                    ? 'El documento se encuentra íntegro'
                    : 'El documento ha sido modificado después de la firma'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            Log::warning('Error en validación de firma', [
                'error' => $e->getMessage(),
                'documentable_type' => $request->input('documentable_type'),
                'documentable_id' => $request->input('documentable_id'),
            ]);

            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            Log::error('Error inesperado en validación de firma', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Error al validar la firma del documento', null, 500);
        }
    }
}
