<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\MetricasArchivosRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Metricas\ArchivosMetricasService;
use Illuminate\Http\JsonResponse;

/**
 * Tablero de métricas de archivos: volumen, peso y formatos de lo cargado en todos los módulos.
 */
class MetricasArchivosController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ArchivosMetricasService $metricas) {}

    public function index(MetricasArchivosRequest $request): JsonResponse
    {
        return $this->successResponse($this->metricas->resumen($request->filtros()), 'Métricas de archivos obtenidas');
    }
}
