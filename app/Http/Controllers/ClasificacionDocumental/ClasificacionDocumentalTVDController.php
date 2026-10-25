<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Helpers\ExportSeguroHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClasificacionDocumental\StoreTVDRequest;
use App\Http\Requests\ClasificacionDocumental\UpdateTVDRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use App\Models\ClasificacionDocumental\FondoAcumuladoPeriodo;
use App\Models\ClasificacionDocumental\FondoAcumuladoUnidad;
use App\Rules\ArchivoLegible;
use App\Rules\ArchivoSeguro;
use App\Services\ClasificacionDocumental\TVDService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ClasificacionDocumentalTVDController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TVDService $service
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = $request->only(['fondo_id', 'periodo_id', 'unidad_id', 'tipo', 'buscar']);
            $tvd = $this->service->getAll($filters);

            return $this->successResponse($tvd, 'TVD obtenidas exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener las TVD', $e->getMessage(), 500);
        }
    }

    public function store(StoreTVDRequest $request)
    {
        try {
            $tvd = $this->service->create($request->validated());

            return $this->successResponse($tvd, 'Elemento TVD creado exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al crear el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $tvd = ClasificacionDocumentalTVD::with(['children', 'unidad', 'periodo.fondo', 'parent'])->find($id);

            if (! $tvd) {
                return $this->errorResponse('Elemento TVD no encontrado', null, 404);
            }

            return $this->successResponse($tvd, 'Elemento TVD obtenido exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function update(UpdateTVDRequest $request, $id)
    {
        try {
            $tvd = $this->service->update((int) $id, $request->validated());

            if (! $tvd) {
                return $this->errorResponse('Elemento TVD no encontrado', null, 404);
            }

            return $this->successResponse($tvd, 'Elemento TVD actualizado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar el elemento TVD', $e->getMessage(), 500);
        }
    }

    /**
     * M10-10/M11-04: mismo tope que la importación de TRD (2 MB); con 10 MB la hoja se cargaba
     * completa en memoria (ver M10-05). El front valida el mismo límite.
     */
    private static function reglasImportacion(): array
    {
        return [
            'archivo' => ['bail', 'required', 'file', new ArchivoLegible, 'max:2048', new ArchivoSeguro(['xlsx', 'xls', 'csv']), 'mimes:xlsx,xls,csv'],
            'periodo_id' => 'required|integer|exists:fondo_acumulado_periodos,id',
        ];
    }

    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate(self::reglasImportacion());

            $result = $this->service->importFromExcel($request->file('archivo'), (int) $request->input('periodo_id'));

            return $this->successResponse($result, 'TVD importada exitosamente');
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al importar la TVD', $e->getMessage(), 500);
        }
    }

    /**
     * Plantilla vacía con la estructura que lee TVDService::importFromExcel: B4 = código de la
     * unidad del período, encabezados en la fila 6 y datos desde la fila 7. Se genera en vez de
     * guardarse en storage para que no se desalinee del importador.
     */
    public function plantilla(): BinaryFileResponse
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('TVD');

        $hoja->setCellValue('A1', 'TABLA DE VALORACIÓN DOCUMENTAL');
        $hoja->setCellValue('A2', 'Serie: A = unidad, B = código de la serie y C vacía. Subserie: A, B y C = código de la subserie (o las tres vacías, debajo de su serie).');
        $hoja->setCellValue('A3', 'Gestión y Central (años) solo aplican a la serie. Los datos se leen desde la fila 7.');
        $hoja->setCellValue('A4', 'Unidad administrativa');
        $hoja->setCellValue('C4', '← Código de la unidad en el período elegido al importar');
        $hoja->fromArray(['Unidad', 'Serie', 'Subserie', '', 'Nombre', '', '', 'Gestión', 'Central', 'Soporte', 'Disposición final'], null, 'A6');

        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A4')->getFont()->setBold(true);
        $hoja->getStyle('B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $hoja->getStyle('A6:K6')->getFont()->setBold(true);
        $hoja->getStyle('A6:K6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        // Códigos como texto: "010" no debe llegar como 10
        $hoja->getStyle('A4:C1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (['A' => 12, 'B' => 14, 'C' => 14, 'D' => 4, 'E' => 45, 'F' => 4, 'G' => 4, 'H' => 10, 'I' => 10, 'J' => 14, 'K' => 18] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'tvd_plantilla');
        (new Xlsx($libro))->save($ruta);

        return response()->download($ruta, 'Ocobo - Plantilla TVD.xlsx')->deleteFileAfterSend();
    }

    public function validar(Request $request): JsonResponse
    {
        try {
            $request->validate(self::reglasImportacion());

            $result = $this->service->validarArchivo($request->file('archivo'), (int) $request->input('periodo_id'));

            return $this->successResponse($result, 'Validación completada');
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al validar la TVD', $e->getMessage(), 500);
        }
    }

    /**
     * Aprueba la TVD de un período del fondo acumulado (M11-02): el Comité la aprueba completa, de
     * una vez. Solo el Jefe de Archivo, y nunca quien registró elementos del período (segregación de
     * funciones). Aprobado, el período y sus unidades quedan inmutables.
     */
    public function aprobar($periodoId)
    {
        $user = auth()->user();
        if (! $user->can('Jefe de Archivo')) {
            return $this->forbiddenResponse('Solo el Jefe de Archivo puede aprobar la TVD');
        }

        return DB::transaction(function () use ($periodoId, $user) {
            $periodo = FondoAcumuladoPeriodo::whereKey($periodoId)->lockForUpdate()->first();

            if (! $periodo) {
                return $this->notFoundResponse('Período no encontrado');
            }
            if ($periodo->fecha_aprobacion !== null) {
                return $this->warningResponse('La TVD de este período ya fue aprobada');
            }

            $elementos = ClasificacionDocumentalTVD::where('periodo_id', $periodo->id)->get(['id', 'user_register']);

            if ($elementos->isEmpty()) {
                return $this->warningResponse('El período no tiene TVD registrada');
            }
            if ($elementos->contains(fn ($e) => (int) $e->user_register === (int) $user->id)) {
                return $this->warningResponse('Quien registró elementos de la TVD no puede aprobarla');
            }

            $periodo->forceFill(['aprobado_por' => $user->id, 'fecha_aprobacion' => now()])->save();

            return $this->successResponse(['elementos' => $elementos->count()], 'TVD del período aprobada exitosamente');
        });
    }

    /**
     * Lista la TVD jerárquica de una unidad administrativa histórica.
     */
    public function listarPorUnidad($unidadId)
    {
        try {
            $unidad = FondoAcumuladoUnidad::with('periodo.fondo')->find($unidadId);

            if (! $unidad) {
                return $this->errorResponse('Unidad administrativa no encontrada', null, 404);
            }

            $elementos = ClasificacionDocumentalTVD::where('unidad_id', $unidadId)
                ->whereNull('parent')
                ->with('children')
                ->orderBy('cod', 'asc')
                ->get();

            return $this->successResponse([
                'unidad' => $unidad,
                'elementos' => $elementos,
                'total_elementos' => ClasificacionDocumentalTVD::where('unidad_id', $unidadId)->count(),
                'aprobada' => $unidad->periodo->aprobado,
            ], 'Elementos TVD obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener elementos TVD', $e->getMessage(), 500);
        }
    }

    /**
     * Exporta la TVD a Excel o CSV.
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['fondo_id', 'periodo_id', 'unidad_id', 'tipo', 'buscar']);
            $format = $request->get('format', 'excel');

            $query = ClasificacionDocumentalTVD::with(['unidad', 'periodo.fondo'])
                ->orderBy('periodo_id')
                ->orderBy('unidad_id')
                ->orderBy('cod', 'asc');

            $rows = $this->service->aplicarFiltros($query, $filters)->get();
            $headers = ['Fondo', 'Período', 'Unidad Administrativa', 'Tipo', 'Código', 'Nombre', 'Soporte', 'Gestión', 'Central', 'Total Años', 'Disposición Final'];

            if ($format === 'csv') {
                return $this->exportCsv($rows, $headers);
            }

            return $this->exportExcel($rows, $headers);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al exportar la TVD', $e->getMessage(), 500);
        }
    }

    /**
     * Columnas Fondo, Período y Unidad de la exportación.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function ubicacion(ClasificacionDocumentalTVD $tvd): array
    {
        return [
            $tvd->periodo?->fondo?->nombre,
            $tvd->periodo?->nombre,
            $tvd->unidad ? $tvd->unidad->codigo.' - '.$tvd->unidad->nombre : null,
        ];
    }

    private function exportExcel($rows, array $headers): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $index => $header) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($col.'1', $header);
        }

        $rowIdx = 2;
        foreach ($rows as $tvd) {
            $values = [
                ...$this->ubicacion($tvd),
                $tvd->tipo,
                $tvd->cod,
                $tvd->nom,
                $tvd->soporte,
                $tvd->gestion,
                $tvd->central,
                $tvd->total_anios,
                $tvd->disposicion_final,
            ];
            foreach ($values as $index => $value) {
                $col = Coordinate::stringFromColumnIndex($index + 1);
                ExportSeguroHelper::setCelda($sheet, $col.$rowIdx, $value);
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

    private function exportCsv($rows, array $headers): StreamedResponse
    {
        $fileName = 'tvd_export_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $tvd) {
                fputcsv($out, ExportSeguroHelper::filaCsv([
                    ...$this->ubicacion($tvd),
                    $tvd->tipo,
                    $tvd->cod,
                    $tvd->nom,
                    $tvd->soporte,
                    $tvd->gestion,
                    $tvd->central,
                    $tvd->total_anios,
                    $tvd->disposicion_final,
                ]));
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener estadísticas totales', $e->getMessage(), 500);
        }
    }
}
