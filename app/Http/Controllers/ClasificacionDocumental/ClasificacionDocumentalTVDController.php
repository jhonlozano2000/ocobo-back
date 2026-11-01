<?php

namespace App\Http\Controllers\ClasificacionDocumental;

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
use App\Services\ReportesExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
     * Exporta la TVD con los filtros del listado (o solo las filas seleccionadas) en Excel, PDF o CSV.
     */
    public function export(Request $request, ReportesExportService $exportService)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'fondo_id' => 'nullable|integer',
            'periodo_id' => 'nullable|integer',
            'unidad_id' => 'nullable|integer',
            'tipo' => 'nullable|string|max:50',
            'buscar' => 'nullable|string|max:100',
            'estado' => 'nullable|in:0,1',
            'ids' => 'nullable|array|max:'.ReportesExportService::MAX_FILAS['excel'],
            'ids.*' => 'integer',
        ]);

        $query = ClasificacionDocumentalTVD::with(['unidad', 'periodo.fondo'])
            ->orderBy('periodo_id')
            ->orderBy('unidad_id')
            ->orderBy('cod', 'asc');
        $this->service->aplicarFiltros($query, $request->only(['fondo_id', 'periodo_id', 'unidad_id', 'tipo', 'buscar', 'estado']));
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->ids);
        }

        $exportService->validarVolumen($query, $request->format);

        $datos = $query->get()->map(fn (ClasificacionDocumentalTVD $tvd) => [
            'Fondo' => $tvd->periodo?->fondo?->nombre,
            'Período' => $tvd->periodo?->nombre,
            'Unidad administrativa' => $tvd->unidad ? $tvd->unidad->codigo.' - '.$tvd->unidad->nombre : null,
            'Tipo' => $tvd->tipo,
            'Código' => $tvd->cod,
            'Nombre' => $tvd->nom,
            'Soporte' => $tvd->soporte,
            'Gestión' => $tvd->gestion,
            'Central' => $tvd->central,
            'Total años' => $tvd->total_anios,
            'Disposición final' => $tvd->disposicion_final,
            'Estado' => $tvd->estado ? 'Activo' : 'Inactivo',
        ])->toArray();

        $periodo = $request->filled('periodo_id') ? FondoAcumuladoPeriodo::with('fondo')->find($request->periodo_id) : null;
        $data = [
            'titulo' => 'Tabla de Valoración Documental',
            'columnas' => ['Fondo', 'Período', 'Unidad administrativa', 'Tipo', 'Código', 'Nombre', 'Soporte', 'Gestión', 'Central', 'Total años', 'Disposición final', 'Estado'],
            'datos' => $datos,
            'total' => count($datos),
            'resumen' => array_filter([
                'Fondo' => $periodo?->fondo?->nombre,
                'Período' => $periodo?->nombre,
                'Búsqueda' => $request->buscar,
                'Estado' => ['1' => 'Activo', '0' => 'Inactivo'][$request->estado] ?? null,
                'Selección' => $request->filled('ids') ? count($datos).' elementos' : null,
            ]),
        ];

        $archivo = 'tvd';

        return match ($request->format) {
            'excel' => response()->download($exportService->exportarExcel($data, $archivo), $archivo.'.xlsx')->deleteFileAfterSend(true),
            'pdf' => response()->download($exportService->exportarPDF($data, $archivo), $archivo.'.pdf')->deleteFileAfterSend(true),
            'csv' => $exportService->exportarCSV($data, $archivo, $data['columnas']),
        };
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
