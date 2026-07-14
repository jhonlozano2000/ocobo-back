<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transversal\FirmaValidarRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Firma\FirmaValidacionService;
use Illuminate\Support\Facades\Log;

class FirmaValidacionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaValidacionService $validacionService
    ) {}

    public function validar(FirmaValidarRequest $request)
    {
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
            Log::error('Error inesperado en validación de firma', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Error al validar la firma del documento', null, 500);
        }
    }
}
