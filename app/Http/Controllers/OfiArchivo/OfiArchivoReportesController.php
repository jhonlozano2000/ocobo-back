<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\OfiArchivo\ReportesService;
use App\Services\ReportesExportService;
use Illuminate\Http\Request;

/**
 * Controlador de Reportes y Estadísticas del Archivo.
 *
 * Genera reportes exportables según Circular 004 DAFP.
 * Incluye expedientes, préstamos, transferencias y estadísticas generales.
 */
class OfiArchivoReportesController extends Controller
{
    use ApiResponseTrait;

    protected ReportesService $reportesService;
    protected ReportesExportService $exportService;

    public function __construct(ReportesService $reportesService, ReportesExportService $exportService)
    {
        $this->reportesService = $reportesService;
        $this->exportService = $exportService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Genera un reporte según el tipo solicitado.
     *
     * @param  string  $tipo  Tipo de reporte: expedientes|prestamos|transferencias
     * @param  Request  $request  Filtros: fecha_inicio, fecha_fin, dependencia_id, estado
     */
    public function generar(Request $request, string $tipo)
    {
        try {
            $filtros = $request->only([
                'fecha_inicio', 'fecha_fin', 'dependencia_id',
                'estado', 'tipo', 'search',
            ]);

            $reporte = match ($tipo) {
                'expedientes' => $this->reportesService->expedientes($filtros),
                'prestamos' => $this->reportesService->prestamos($filtros),
                'transferencias' => $this->reportesService->transferencias($filtros),
                default => null,
            };

            if (! $reporte) {
                return $this->errorResponse('Tipo de reporte no válido', null, 422);
            }

            return $this->successResponse($reporte, 'Reporte generado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al generar el reporte', $e->getMessage(), 500);
        }
    }

    /**
     * Exporta un reporte en el formato solicitado (Excel, PDF o CSV).
     */
    public function export(Request $request, string $tipo)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
        ]);

        $filtros = $request->only([
            'fecha_inicio', 'fecha_fin', 'dependencia_id',
            'estado', 'tipo', 'search',
        ]);

        $reporte = match ($tipo) {
            'expedientes' => $this->reportesService->expedientes($filtros),
            'prestamos' => $this->reportesService->prestamos($filtros),
            'transferencias' => $this->reportesService->transferencias($filtros),
            default => null,
        };

        if (! $reporte) {
            return $this->errorResponse('Tipo de reporte no válido', null, 422);
        }

        $nombre = 'reporte_' . $tipo;

        return match ($request->format) {
            'excel' => $this->downloadExcel($reporte, $nombre),
            'pdf' => $this->downloadPDF($reporte, $nombre),
            'csv' => $this->exportService->exportarCSV($reporte, $nombre),
        };
    }

    protected function downloadExcel(array $data, string $nombre)
    {
        $path = $this->exportService->exportarExcel($data, $nombre);

        return response()->download($path, $nombre . '.xlsx')->deleteFileAfterSend(true);
    }

    protected function downloadPDF(array $data, string $nombre)
    {
        $path = $this->exportService->exportarPDF($data, $nombre);

        return response()->download($path, $nombre . '.pdf')->deleteFileAfterSend(true);
    }

    /**
     * Estadísticas generales para dashboard de reportes.
     */
    public function estadisticas()
    {
        try {
            $stats = $this->reportesService->estadisticasGenerales();

            return $this->successResponse($stats, 'Estadísticas obtenidas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }
}
