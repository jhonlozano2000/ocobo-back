<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoEliminacion;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Services\ReportesExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador de Transferencia y Eliminación Documental.
 *
 * Gestiona transferencias primarias/secundarias de expedientes
 * y eliminaciones definitivas con acta digital.
 * Acuerdo AGN 004/2019.
 */
class OfiArchivoTransferenciaController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // ==================== TRANSFERENCIAS ====================

    /**
     * Listado paginado de transferencias con filtros.
     */
    public function indexTransferencias(Request $request)
    {
        try {
            $query = OfiArchivoTransferencia::with(['expediente', 'responsableOrigen', 'responsableDestino', 'lote:id,codigo,estado']);
            $this->filtrarTransferencias($query, $request);

            $perPage = min($request->get('per_page', 15), 100);
            $transferencias = $query->latest()->paginate($perPage);

            return $this->successResponse($transferencias, 'Listado de transferencias obtenido');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener transferencias', $e->getMessage());
        }
    }

    /**
     * B17: las transferencias se envían por lote (FUID, cotejo y acta de entrega). Registrar una
     * transferencia suelta saltaría esos controles, así que ya no se admite.
     */
    public function storeTransferencia()
    {
        return $this->warningResponse('Las transferencias se envían por lote, con su FUID; use /api/archivo/transferencia-lotes');
    }

    /**
     * Aprueba o rechaza una transferencia suelta, anterior a los lotes (Acuerdo AGN 004/2019).
     *
     * Máquina de estados: solo transferencias 'pendiente' pueden decidirse;
     * las ya aprobadas/rechazadas son inmutables. El rechazo exige observaciones.
     *
     * @param  Request  $request  Solicitud con estado (aprobada|rechazada) y observaciones opcionales
     * @param  int  $id  ID de la transferencia
     * @return JsonResponse Transferencia actualizada o error 422 si transición inválida
     *
     * @author Jhon Javer Lozano Arce
     *
     * @date 2026-08-24
     */
    public function aprobarTransferencia(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:aprobada,rechazada',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        // Rechazo exige observaciones (trazabilidad AGN 004/2019)
        if ($request->estado === 'rechazada' && trim((string) $request->observaciones) === '') {
            return $this->warningResponse('Las observaciones son obligatorias al rechazar la transferencia');
        }

        try {
            DB::beginTransaction();

            $transferencia = OfiArchivoTransferencia::lockForUpdate()->findOrFail($id);

            // Máquina de estados: una transferencia ya decidida es inmutable
            if ($transferencia->estado !== 'pendiente') {
                DB::rollBack();

                return $this->warningResponse("La transferencia ya fue {$transferencia->estado} y no puede modificarse");
            }

            // B17: las de un lote se reciben con el cotejo del lote
            if ($transferencia->lote_id) {
                DB::rollBack();

                return $this->warningResponse('La transferencia pertenece a un lote; se recibe o devuelve desde el lote');
            }

            // Segregación de funciones (M08-11): quien registra no decide
            if ((int) $transferencia->usuario_registro_id === (int) Auth::id()) {
                DB::rollBack();

                return $this->warningResponse('Quien registró la transferencia no puede aprobarla ni rechazarla');
            }

            $transferencia->update([
                'estado' => $request->estado,
                'observaciones' => $request->observaciones,
            ]);

            // B16: la primaria lo deja cerrado en el archivo central; la secundaria, transferido al histórico
            if ($request->estado === 'aprobada') {
                $transferencia->expediente->update($transferencia->tipo === 'primaria'
                    ? ['fase' => 'central']
                    : ['fase' => 'historico', 'estado' => 'transferido']);
            }

            DB::commit();

            return $this->successResponse(
                $transferencia->fresh(['expediente']),
                'Transferencia '.($request->estado === 'aprobada' ? 'aprobada' : 'rechazada')
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->serverErrorResponse('Error al procesar la transferencia', $e->getMessage());
        }
    }

    // ==================== ELIMINACIONES ====================

    /**
     * Listado paginado de eliminaciones.
     */
    public function indexEliminaciones(Request $request)
    {
        try {
            $query = OfiArchivoEliminacion::with(['expediente', 'aprobadoPor', 'usuarioRegistro']);
            $this->filtrarEliminaciones($query, $request);

            $perPage = min($request->get('per_page', 15), 100);
            $eliminaciones = $query->latest()->paginate($perPage);

            return $this->successResponse($eliminaciones, 'Listado de eliminaciones obtenido');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener eliminaciones', $e->getMessage());
        }
    }

    /**
     * B18: la eliminación se tramita por lote (publicación, concepto, comité y acta firmada); la
     * individual quedó cerrada. Las ya registradas siguen en el listado.
     */
    public function storeEliminacion()
    {
        return $this->warningResponse('La eliminación individual ya no está disponible: proponga el lote de la serie en Gestión de Archivo → Eliminaciones');
    }

    /**
     * Exporta las transferencias con los filtros del listado (o la selección) en el formato de los reportes.
     */
    public function exportTransferencias(Request $request, ReportesExportService $exportService)
    {
        $this->validarExport($request);
        $request->validate(['tipo' => 'nullable|string|max:30', 'estado' => 'nullable|string|max:30']);

        $query = OfiArchivoTransferencia::with(['expediente', 'responsableOrigen', 'responsableDestino', 'lote:id,codigo']);
        $this->filtrarTransferencias($query, $request);
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->ids);
        }

        $exportService->validarVolumen($query, $request->format);

        $datos = $query->latest()->get()->map(fn (OfiArchivoTransferencia $t) => [
            'Expediente' => $t->expediente?->numero_expediente,
            'Nombre del expediente' => $t->expediente?->nombre_expediente,
            'Tipo' => $t->tipo,
            'Origen' => $t->origen,
            'Destino' => $t->destino,
            'Responsable origen' => $this->nombre($t->responsableOrigen),
            'Responsable destino' => $this->nombre($t->responsableDestino),
            'Lote' => $t->lote?->codigo,
            'Fecha' => $t->fecha_transferencia?->format('Y-m-d'),
            'Estado' => $t->estado,
        ])->toArray();

        return $this->descargar($exportService, $request, 'transferencias', [
            'titulo' => 'Transferencias documentales',
            'columnas' => ['Expediente', 'Nombre del expediente', 'Tipo', 'Origen', 'Destino', 'Responsable origen', 'Responsable destino', 'Lote', 'Fecha', 'Estado'],
            'datos' => $datos,
            'total' => count($datos),
            'resumen' => array_filter([
                'Búsqueda' => $request->search,
                'Tipo' => $request->tipo,
                'Estado' => $request->estado,
                'Selección' => $request->filled('ids') ? count($datos).' transferencias' : null,
            ]),
        ]);
    }

    /**
     * Exporta las eliminaciones con los filtros del listado (o la selección) en el formato de los reportes.
     */
    public function exportEliminaciones(Request $request, ReportesExportService $exportService)
    {
        $this->validarExport($request);

        $query = OfiArchivoEliminacion::with(['expediente', 'aprobadoPor', 'usuarioRegistro']);
        $this->filtrarEliminaciones($query, $request);
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->ids);
        }

        $exportService->validarVolumen($query, $request->format);

        $datos = $query->latest()->get()->map(fn (OfiArchivoEliminacion $e) => [
            'Expediente' => $e->expediente?->numero_expediente,
            'Nombre del expediente' => $e->expediente?->nombre_expediente,
            'Fecha' => $e->fecha?->format('Y-m-d'),
            'Método' => $e->metodo,
            'Aprobado por' => $this->nombre($e->aprobadoPor),
            'Registrado por' => $this->nombre($e->usuarioRegistro),
        ])->toArray();

        return $this->descargar($exportService, $request, 'eliminaciones', [
            'titulo' => 'Eliminaciones documentales',
            'columnas' => ['Expediente', 'Nombre del expediente', 'Fecha', 'Método', 'Aprobado por', 'Registrado por'],
            'datos' => $datos,
            'total' => count($datos),
            'resumen' => array_filter([
                'Búsqueda' => $request->search,
                'Selección' => $request->filled('ids') ? count($datos).' eliminaciones' : null,
            ]),
        ]);
    }

    private function validarExport(Request $request): void
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'search' => 'nullable|string|max:100',
            'ids' => 'nullable|array|max:'.ReportesExportService::MAX_FILAS['excel'],
            'ids.*' => 'integer',
        ]);
    }

    private function descargar(ReportesExportService $exportService, Request $request, string $archivo, array $data)
    {
        return match ($request->format) {
            'excel' => response()->download($exportService->exportarExcel($data, $archivo), $archivo.'.xlsx')->deleteFileAfterSend(true),
            'pdf' => response()->download($exportService->exportarPDF($data, $archivo), $archivo.'.pdf')->deleteFileAfterSend(true),
            'csv' => $exportService->exportarCSV($data, $archivo, $data['columnas']),
        };
    }

    private function nombre($usuario): string
    {
        return $usuario ? trim($usuario->nombres.' '.$usuario->apellidos) : '';
    }

    /**
     * Filtros del listado de transferencias; la búsqueda cubre lo mismo que la de la tabla.
     */
    private function filtrarTransferencias(Builder $query, Request $request): void
    {
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('origen', 'like', "%{$search}%")
                ->orWhere('destino', 'like', "%{$search}%")
                ->orWhereHas('expediente', fn ($e) => $e->where('numero_expediente', 'like', "%{$search}%")
                    ->orWhere('nombre_expediente', 'like', "%{$search}%")));
        }
    }

    private function filtrarEliminaciones(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('expediente', fn ($e) => $e->where('numero_expediente', 'like', "%{$search}%")
                ->orWhere('nombre_expediente', 'like', "%{$search}%"));
        }
    }

    /**
     * Estadísticas de transferencias y eliminaciones.
     */
    public function estadisticas()
    {
        try {
            $stats = [
                'transferencias_pendientes' => OfiArchivoTransferencia::where('estado', 'pendiente')->count(),
                // El flujo termina en 'aprobada' ('completada' no se asigna): aprobadas hoy
                'transferencias_completadas_hoy' => OfiArchivoTransferencia::whereIn('estado', ['aprobada', 'completada'])
                    ->whereDate('updated_at', today())->count(),
                'eliminaciones_totales' => OfiArchivoEliminacion::count(),
                'eliminaciones_hoy' => OfiArchivoEliminacion::whereDate('fecha', today())->count(),
            ];

            return $this->successResponse($stats, 'Estadísticas obtenidas');
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
