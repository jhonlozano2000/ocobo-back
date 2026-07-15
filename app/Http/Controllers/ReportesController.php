<?php

namespace App\Http\Controllers;

use App\Services\ReportesExportService;
use App\Services\ReportesUnificadoService;
use App\Models\ReporteProgramado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReporteProgramadoMailable;

class ReportesController extends Controller
{
    public function __construct(
        protected ReportesUnificadoService $unificado,
        protected ReportesExportService $export
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

        try {
            $data = $this->unificado->generarUnificado(
                $request->modulo,
                $request->only(['desde', 'hasta', 'estado', 'page', 'per_page'])
            );

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
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

        try {
            $filtros = array_merge(
                $request->only(['desde', 'hasta', 'estado']),
                ['per_page' => 999999, 'page' => 1]
            );
            $data = $this->unificado->generarUnificado($request->modulo, $filtros);
            $columnas = $data['columnas'];
            $nombre = 'reporte_' . $request->modulo;

            return match ($request->format) {
                'excel' => $this->downloadExcel($data, $nombre, $columnas),
                'pdf' => $this->downloadPDF($data, $nombre),
                'csv' => $this->export->exportarCSV($data, $nombre, $columnas),
            };
        } catch (\Exception $e) {
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

    public function programados()
    {
        $items = ReporteProgramado::orderBy('proxima_ejecucion')->get();

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

        $validated['filtros'] = $validated['filtros'] ?? [];
        $validated['activo'] = true;

        $item = ReporteProgramado::create($validated);

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateProgramado(Request $request, $id)
    {
        $item = ReporteProgramado::findOrFail($id);

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

        $item->update($validated);

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function destroyProgramado($id)
    {
        $item = ReporteProgramado::findOrFail($id);
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Programación eliminada']);
    }

    public function ejecutarProgramado($id)
    {
        $item = ReporteProgramado::findOrFail($id);

        try {
            $data = $this->unificado->generarUnificado($item->modulo, $item->filtros ?? []);
            $columnas = $data['columnas'];
            $nombre = 'reporte_' . $item->modulo;

            $path = match ($item->formato) {
                'excel' => $this->export->exportarExcel($data, $nombre, $columnas),
                'pdf' => $this->export->exportarPDF($data, $nombre),
                default => null,
            };

            if ($path) {
                Mail::to($item->destinatarios)->send(new ReporteProgramadoMailable($path, $item->asunto));
            }

            $item->ultima_ejecucion = now();
            $item->proxima_ejecucion = $item->calcularProximaEjecucion();
            $item->save();

            return response()->json([
                'success' => true,
                'message' => 'Reporte ejecutado y enviado correctamente',
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar reporte: ' . $e->getMessage(),
            ], 500);
        }
    }
}
