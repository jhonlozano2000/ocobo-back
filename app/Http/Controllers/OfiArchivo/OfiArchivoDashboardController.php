<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\OfiArchivo\ReportesService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador de Dashboard e Indicadores de Gestión del Archivo.
 *
 * Agrega KPIs de todos los módulos del archivo con caché Redis
 * para no impactar performance con queries pesadas.
 */
class OfiArchivoDashboardController extends Controller
{
    use ApiResponseTrait;

    protected ReportesService $reportesService;

    public function __construct(ReportesService $reportesService)
    {
        $this->reportesService = $reportesService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Estadísticas consolidadas del dashboard.
     * Caché de 5 minutos para no sobrecargar la base de datos.
     */
    public function stats()
    {
        try {
            // Los totales ya vienen cacheados del servicio; los listados dependen del usuario.
            $stats = $this->reportesService->estadisticasGenerales(auth()->user());

            return $this->successResponse($stats, 'Estadísticas del dashboard obtenidas');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estadísticas', $e->getMessage());
        }
    }
}
