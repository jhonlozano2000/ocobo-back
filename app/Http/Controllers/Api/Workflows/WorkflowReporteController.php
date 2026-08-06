<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowReporteService;

class WorkflowReporteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkflowReporteService $reporteService
    ) {
        $this->middleware('can:Workflows -> Workflows -> Listar');
    }

    public function resumen(Workflow $workflow)
    {
        try {
            $data = $this->reporteService->resumen((int) $workflow->id);
            return $this->successResponse($data, 'Reporte obtenido correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener reporte', $e->getMessage());
        }
    }

    public function tareasVencidasPorUsuario(Workflow $workflow)
    {
        try {
            $data = $this->reporteService->tareasVencidasPorUsuario((int) $workflow->id);
            return $this->successResponse($data, 'Datos obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener datos', $e->getMessage());
        }
    }

    public function tendenciaMensual(Workflow $workflow)
    {
        try {
            $data = $this->reporteService->tendenciaMensual((int) $workflow->id, (int) request('meses', 6));
            return $this->successResponse($data, 'Tendencia obtenida correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tendencia', $e->getMessage());
        }
    }
}
