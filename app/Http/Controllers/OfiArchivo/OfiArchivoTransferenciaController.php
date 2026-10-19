<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoEliminacion;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
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
