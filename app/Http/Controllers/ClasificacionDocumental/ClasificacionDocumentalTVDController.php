<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use App\Services\ClasificacionDocumental\TVDService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClasificacionDocumentalTVDController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TVDService $service
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $tvd = $this->service->getAll($filters);

            return $this->successResponse($tvd, 'TVD obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las TVD', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $tvd = $this->service->create($request->all());

            return $this->successResponse($tvd, 'Elemento TVD creado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $tvd = ClasificacionDocumentalTVD::with(['children', 'dependencia', 'parent'])->find($id);

            if (! $tvd) {
                return $this->errorResponse('Elemento TVD no encontrado', null, 404);
            }

            return $this->successResponse($tvd, 'Elemento TVD obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tvd = $this->service->update($id, $request->all());

            if (! $tvd) {
                return $this->errorResponse('Elemento TVD no encontrado', null, 404);
            }

            return $this->successResponse($tvd, 'Elemento TVD actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (! $this->service->delete($id)) {
                return $this->errorResponse('No se puede eliminar el elemento TVD porque tiene hijos o no existe', null, 422);
            }

            return $this->successResponse(null, 'Elemento TVD eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            $result = $this->service->importFromExcel($request->file('archivo'));

            return $this->successResponse($result, 'TVD importada exitosamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al importar la TVD', $e->getMessage(), 500);
        }
    }

    public function validar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            $result = $this->service->validarArchivo($request->file('archivo'));

            return $this->successResponse($result, 'Validación completada');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al validar la TVD', $e->getMessage(), 500);
        }
    }

    /**
     * Lista la TVD jerárquica de una dependencia.
     */
    public function listarPorDependencia($dependenciaId)
    {
        try {
            $dependencia = CalidadOrganigrama::find($dependenciaId);

            if (! $dependencia) {
                return $this->errorResponse('Dependencia no encontrada', null, 404);
            }

            $elementos = ClasificacionDocumentalTVD::where('dependencia_id', $dependenciaId)
                ->whereNull('parent')
                ->with(['children', 'dependencia'])
                ->orderBy('cod', 'asc')
                ->get();

            return $this->successResponse([
                'dependencia' => $dependencia,
                'elementos' => $elementos,
                'total_elementos' => ClasificacionDocumentalTVD::where('dependencia_id', $dependenciaId)->count(),
            ], 'Elementos TVD obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener elementos TVD', $e->getMessage(), 500);
        }
    }

    /**
     * Exporta la TVD a Excel o CSV.
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['dependencia_id', 'tipo', 'buscar']);
            $format = $request->get('format', 'excel');

            $query = ClasificacionDocumentalTVD::with('dependencia')
                ->orderBy('cod', 'asc');

            if (! empty($filters['dependencia_id'])) {
                $query->where('dependencia_id', $filters['dependencia_id']);
            }
            if (! empty($filters['tipo'])) {
                $query->where('tipo', $filters['tipo']);
            }
            if (! empty($filters['buscar'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('cod', 'like', "%{$filters['buscar']}%")
                        ->orWhere('nom', 'like', "%{$filters['buscar']}%");
                });
            }

            $rows = $query->get();
            $headers = ['Tipo', 'Código', 'Nombre', 'Dependencia', 'Soporte', 'Gestión', 'Central', 'Total Años', 'Disposición Final'];

            if ($format === 'csv') {
                return $this->exportCsv($rows, $headers);
            }

            return $this->exportExcel($rows, $headers);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al exportar la TVD', $e->getMessage(), 500);
        }
    }

    private function exportExcel($rows, array $headers): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $index => $header) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($col.'1', $header);
        }

        $rowIdx = 2;
        foreach ($rows as $tvd) {
            $values = [
                $tvd->tipo,
                $tvd->cod,
                $tvd->nom,
                $tvd->dependencia?->nom_organico,
                $tvd->soporte,
                $tvd->gestion,
                $tvd->central,
                $tvd->total_anios,
                $tvd->disposicion_final,
            ];
            foreach ($values as $index => $value) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($col.$rowIdx, $value);
            }
            $rowIdx++;
        }

        $fileName = 'tvd_export_'.date('Ymd_His').'.xlsx';
        $path = storage_path('app/exports/'.$fileName);
        if (! is_dir(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $fileName)->deleteFileAfterSend();
    }

    private function exportCsv($rows, array $headers): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fileName = 'tvd_export_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $tvd) {
                fputcsv($out, [
                    $tvd->tipo,
                    $tvd->cod,
                    $tvd->nom,
                    $tvd->dependencia?->nom_organico,
                    $tvd->soporte,
                    $tvd->gestion,
                    $tvd->central,
                    $tvd->total_anios,
                    $tvd->disposicion_final,
                ]);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function estadisticas()
    {
        try {
            return $this->successResponse(
                $this->service->getTotalStats(),
                'Estadísticas totales obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas totales', $e->getMessage(), 500);
        }
    }
}
