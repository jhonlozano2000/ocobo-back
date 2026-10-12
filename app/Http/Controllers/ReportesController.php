<?php

namespace App\Http\Controllers;

use App\Models\ReporteProgramado;
use App\Services\ReportesExportService;
use App\Services\ReportesProgramadosService;
use App\Services\ReportesUnificadoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ReportesController extends Controller
{
    public function __construct(
        protected ReportesUnificadoService $unificado,
        protected ReportesExportService $export,
        protected ReportesProgramadosService $programadosService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'modulo' => 'required|string|in:recibidas,enviadas,internas,pqrs,expedientes,prestamos,transferencias',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'estado' => 'nullable|string|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $this->autorizarModulo($request, $request->modulo);

        try {
            $data = $this->unificado->generarUnificado(
                $request->modulo,
                $request->only(['desde', 'hasta', 'estado', 'page', 'per_page']),
                $request->user()
            );

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function export(Request $request)
    {
        $request->validate([
            'modulo' => 'required|string|in:recibidas,enviadas,internas,pqrs,expedientes,prestamos,transferencias',
            'format' => 'required|string|in:excel,pdf,csv',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'estado' => 'nullable|string|max:50',
        ]);

        $this->autorizarModulo($request, $request->modulo);

        try {
            $data = $this->unificado->generarUnificado(
                $request->modulo,
                $request->only(['desde', 'hasta', 'estado']),
                $request->user(),
                true
            );
            $columnas = $data['columnas'];
            $nombre = 'reporte_'.$request->modulo;

            return match ($request->format) {
                'excel' => $this->downloadExcel($data, $nombre, $columnas),
                'pdf' => $this->downloadPDF($data, $nombre),
                'csv' => $this->export->exportarCSV($data, $nombre, $columnas),
            };
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    protected function downloadExcel(array $data, string $nombre, array $columnas)
    {
        $path = $this->export->exportarExcel($data, $nombre, $columnas);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    protected function downloadPDF(array $data, string $nombre)
    {
        $path = $this->export->exportarPDF($data, $nombre);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /** REP-002: el usuario debe tener el permiso de listado del módulo que reporta. */
    protected function autorizarModulo(Request $request, string $modulo): void
    {
        abort_unless(
            ReportesUnificadoService::puedeReportar($request->user(), $modulo),
            403,
            'No tiene permiso para reportar este módulo.'
        );
    }

    /** REP-002: cada usuario solo ve, edita, ejecuta o elimina sus propias programaciones. */
    protected function programadoPropio(Request $request, $id): ReporteProgramado
    {
        return ReporteProgramado::where('user_id', $request->user()->id)->findOrFail($id);
    }

    public function programados(Request $request)
    {
        $items = ReporteProgramado::where('user_id', $request->user()->id)->orderBy('proxima_ejecucion')->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeProgramado(Request $request)
    {
        $validated = $request->validate([
            'modulo' => 'required|string|in:recibidas,enviadas,internas,pqrs,expedientes,prestamos,transferencias',
            'filtros' => 'nullable|array',
            'formato' => 'required|string|in:excel,pdf,csv',
            'periodicidad' => 'required|string|in:daily,weekly,monthly,quarterly',
            'asunto' => 'required|string|max:255',
            'destinatarios' => 'required|array',
            'destinatarios.*' => 'required|email',
            'proxima_ejecucion' => 'required|date|after:now',
        ]);

        $this->autorizarModulo($request, $validated['modulo']);

        $validated['filtros'] = $validated['filtros'] ?? [];
        $validated['activo'] = true;
        $validated['user_id'] = $request->user()->id;

        $item = ReporteProgramado::create($validated);

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateProgramado(Request $request, $id)
    {
        $item = $this->programadoPropio($request, $id);

        $validated = $request->validate([
            'modulo' => 'sometimes|string|in:recibidas,enviadas,internas,pqrs,expedientes,prestamos,transferencias',
            'filtros' => 'nullable|array',
            'formato' => 'sometimes|string|in:excel,pdf,csv',
            'periodicidad' => 'sometimes|string|in:daily,weekly,monthly,quarterly',
            'asunto' => 'sometimes|string|max:255',
            'destinatarios' => 'sometimes|array',
            'destinatarios.*' => 'required|email',
            'proxima_ejecucion' => 'sometimes|date',
            'activo' => 'sometimes|boolean',
        ]);

        if (isset($validated['modulo'])) {
            $this->autorizarModulo($request, $validated['modulo']);
        }

        $item->update($validated);

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function destroyProgramado(Request $request, $id)
    {
        $item = $this->programadoPropio($request, $id);
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Programación eliminada']);
    }

    public function ejecutarProgramado(Request $request, $id)
    {
        $item = $this->programadoPropio($request, $id);

        try {
            $item = $this->programadosService->ejecutar($item);

            return response()->json([
                'success' => true,
                'message' => 'Reporte ejecutado y enviado correctamente',
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            if ($e instanceof AuthorizationException) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar reporte: '.$e->getMessage(),
            ], 500);
        }
    }
}
