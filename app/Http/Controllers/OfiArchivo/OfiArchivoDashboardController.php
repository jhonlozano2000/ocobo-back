<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\OfiArchivo\ReportesService;
use Illuminate\Support\Facades\Cache;

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
            $stats = Cache::remember('archivo_dashboard_stats', now()->addMinutes(5), function () {
                return $this->reportesService->estadisticasGenerales();
            });

            return $this->successResponse($stats, 'Estadísticas del dashboard obtenidas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }
}
