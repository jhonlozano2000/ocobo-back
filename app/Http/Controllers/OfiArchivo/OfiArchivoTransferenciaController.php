<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoEliminacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $query = OfiArchivoTransferencia::with(['expediente', 'responsableOrigen', 'responsableDestino']);

            if ($request->has('tipo') && $request->tipo !== '') {
                $query->where('tipo', $request->tipo);
            }

            if ($request->has('estado') && $request->estado !== '') {
                $query->where('estado', $request->estado);
            }

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->whereHas('expediente', function ($q) use ($search) {
                    $q->where('numero_expediente', 'like', "%{$search}%")
                        ->orWhere('nombre_expediente', 'like', "%{$search}%");
                });
            }

            $perPage = min($request->get('per_page', 15), 100);
            $transferencias = $query->latest()->paginate($perPage);

            return $this->successResponse($transferencias, 'Listado de transferencias obtenido');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener transferencias', $e->getMessage(), 500);
        }
    }

    /**
     * Registra una nueva transferencia documental.
     */
    public function storeTransferencia(Request $request)
    {
        $request->validate([
            'expediente_id' => 'required|exists:ofi_archivo_expedientes,id',
            'tipo' => 'required|in:primaria,secundaria',
            'origen' => 'required|string|max:200',
            'destino' => 'required|string|max:200',
            'responsable_origen_id' => 'required|exists:users,id',
            'responsable_destino_id' => 'nullable|exists:users,id',
            'fecha_transferencia' => 'required|date',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $transferencia = OfiArchivoTransferencia::create([
                ...$request->only([
                    'expediente_id', 'tipo', 'origen', 'destino',
                    'responsable_origen_id', 'responsable_destino_id',
                    'fecha_transferencia', 'observaciones',
                ]),
                'estado' => 'pendiente',
                'usuario_registro_id' => Auth::id(),
            ]);

            DB::commit();

            return $this->successResponse(
                $transferencia->load(['expediente', 'responsableOrigen', 'responsableDestino']),
                'Transferencia registrada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al registrar la transferencia', $e->getMessage(), 500);
        }
    }

    /**
     * Aprueba o rechaza una transferencia.
     */
    public function aprobarTransferencia(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:aprobada,rechazada',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $transferencia = OfiArchivoTransferencia::findOrFail($id);
            $transferencia->update([
                'estado' => $request->estado,
                'observaciones' => $request->observaciones,
            ]);

            if ($request->estado === 'aprobada') {
                $transferencia->expediente->update(['estado' => 'transferido']);
            }

            DB::commit();

            return $this->successResponse(
                $transferencia->fresh(['expediente']),
                'Transferencia '.($request->estado === 'aprobada' ? 'aprobada' : 'rechazada')
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al procesar la transferencia', $e->getMessage(), 500);
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

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->whereHas('expediente', function ($q) use ($search) {
                    $q->where('numero_expediente', 'like', "%{$search}%");
                });
            }

            $perPage = min($request->get('per_page', 15), 100);
            $eliminaciones = $query->latest()->paginate($perPage);

            return $this->successResponse($eliminaciones, 'Listado de eliminaciones obtenido');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener eliminaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Registra una eliminación documental con acta digital.
     * Valida que el expediente esté cerrado y la TRD permita eliminación.
     */
    public function storeEliminacion(Request $request)
    {
        $request->validate([
            'expediente_id' => 'required|exists:ofi_archivo_expedientes,id',
            'metodo' => 'required|in:destruccion_fisica,borrado_seguro',
            'responsable_ids' => 'required|array|min:2',
            'testigos' => 'nullable|string|max:500',
            'aprobado_por_id' => 'required|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::findOrFail($request->expediente_id);

            if ($expediente->estado !== 'Cerrado') {
                DB::rollBack();

                return $this->errorResponse('Solo se pueden eliminar expedientes cerrados', null, 422);
            }

            $eliminacion = OfiArchivoEliminacion::create([
                'expediente_id' => $request->expediente_id,
                'fecha' => now(),
                'responsable_ids' => $request->responsable_ids,
                'metodo' => $request->metodo,
                'testigos' => $request->testigos,
                'aprobado_por_id' => $request->aprobado_por_id,
                'usuario_registro_id' => Auth::id(),
            ]);

            DB::commit();

            return $this->successResponse(
                $eliminacion->load(['expediente', 'aprobadoPor']),
                'Eliminación registrada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al registrar la eliminación', $e->getMessage(), 500);
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
                'transferencias_completadas_hoy' => OfiArchivoTransferencia::where('estado', 'completada')
                    ->whereDate('fecha_transferencia', today())->count(),
                'eliminaciones_totales' => OfiArchivoEliminacion::count(),
                'eliminaciones_hoy' => OfiArchivoEliminacion::whereDate('fecha', today())->count(),
            ];

            return $this->successResponse($stats, 'Estadísticas obtenidas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }
}
