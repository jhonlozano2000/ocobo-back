<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de Préstamo de Archivo.
 *
 * Gestiona el préstamo de expedientes físicos del archivo central,
 * incluyendo registro, devolución y estadísticas.
 * Cumple con Acuerdo AGN 042/2002 — Libro de préstamo de documentos.
 *
 * @see OfiArchivoPrestamo Modelo
 */
class OfiArchivoPrestamoController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listado paginado de préstamos con filtros por estado y búsqueda.
     *
     * @param  Request  $request  Filtros: estado, search, per_page
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = OfiArchivoPrestamo::with(['expediente', 'solicitante', 'usuarioRegistro']);

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
            $prestamos = $query->latest()->paginate($perPage);

            return $this->successResponse($prestamos, 'Listado de préstamos obtenido');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    /**
     * Registra un nuevo préstamo de expediente.
     * Valida que el expediente esté en estado 'Abierto'.
     *
     * @param  Request  $request  expediente_id, solicitante_id, fecha_devolucion_esperada (requeridos)
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'expediente_id' => 'required|exists:ofi_archivo_expedientes,id',
            'solicitante_id' => 'required|exists:users,id',
            'dependencia_destino' => 'nullable|string|max:200',
            'fecha_devolucion_esperada' => 'required|date|after:now',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::findOrFail($request->expediente_id);

            if ($expediente->estado !== 'Abierto') {
                DB::rollBack();

                return $this->errorResponse('No se puede prestar un expediente que no está abierto', null, 422);
            }

            $prestamo = OfiArchivoPrestamo::create([
                'expediente_id' => $request->expediente_id,
                'solicitante_id' => $request->solicitante_id,
                'dependencia_destino' => $request->dependencia_destino,
                'fecha_prestamo' => now(),
                'fecha_devolucion_esperada' => $request->fecha_devolucion_esperada,
                'estado' => 'prestado',
                'observaciones' => $request->observaciones,
                'usuario_registro_id' => Auth::id(),
            ]);

            DB::commit();

            return $this->successResponse(
                $prestamo->load(['expediente', 'solicitante', 'usuarioRegistro']),
                'Préstamo registrado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al registrar el préstamo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el detalle de un préstamo específico.
     *
     * @param  int  $id  ID del préstamo
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            $prestamo = OfiArchivoPrestamo::with(['expediente', 'solicitante', 'usuarioRegistro'])
                ->findOrFail($id);

            return $this->successResponse($prestamo, 'Préstamo obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el préstamo', $e->getMessage(), 500);
        }
    }

    /**
     * Registra la devolución de un préstamo.
     * Actualiza fecha_devolucion_real y estado a 'devuelto'.
     *
     * @param  int  $id  ID del préstamo
     * @return JsonResponse
     */
    public function devolver($id)
    {
        try {
            DB::beginTransaction();

            $prestamo = OfiArchivoPrestamo::findOrFail($id);

            if ($prestamo->estado !== 'prestado') {
                DB::rollBack();

                return $this->errorResponse('Este préstamo ya fue devuelto', null, 422);
            }

            $prestamo->update([
                'fecha_devolucion_real' => now(),
                'estado' => 'devuelto',
            ]);

            DB::commit();

            return $this->successResponse(
                $prestamo->load(['expediente', 'solicitante']),
                'Devolución registrada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al registrar la devolución', $e->getMessage(), 500);
        }
    }

    /**
     * Estadísticas rápidas de préstamos para dashboard.
     * Retorna: activos, vencidos, devueltos_hoy, prestados_hoy.
     *
     * @return JsonResponse
     */
    public function estadisticas()
    {
        try {
            $stats = [
                'activos' => OfiArchivoPrestamo::activos()->count(),
                'vencidos' => OfiArchivoPrestamo::vencidos()->count(),
                'devueltos_hoy' => OfiArchivoPrestamo::where('estado', 'devuelto')
                    ->whereDate('fecha_devolucion_real', today())->count(),
                'prestados_hoy' => OfiArchivoPrestamo::whereDate('fecha_prestamo', today())->count(),
            ];

            return $this->successResponse($stats, 'Estadísticas de préstamos obtenidas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }
}
