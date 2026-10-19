<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoEliminacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Models\User;
use Carbon\Carbon;
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

    public const PERMISO_APROBAR_ELIMINACION = 'Gestion de Archivo -> Eliminaciones -> Aprobar';

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
     * Registra una nueva transferencia documental (Acuerdo AGN 004/2019, M08-11).
     *
     * Primaria (gestión → central): expediente cerrado y tiempo de retención en gestión cumplido.
     * Secundaria (central → histórico): expediente ya transferido al central, retención total
     * cumplida y disposición final de conservación total o selección. Una sola pendiente por expediente.
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

            $expediente = OfiArchivoExpediente::lockForUpdate()->findOrFail($request->expediente_id);

            if ($error = $this->validarTransferencia($expediente, $request->tipo)) {
                DB::rollBack();

                return $this->warningResponse($error);
            }

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

            return $this->createdResponse(
                $transferencia->load(['expediente', 'responsableOrigen', 'responsableDestino']),
                'Transferencia registrada exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->serverErrorResponse('Error al registrar la transferencia', $e->getMessage());
        }
    }

    /**
     * Aprueba o rechaza una transferencia documental (Acuerdo AGN 004/2019).
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
     * Registra una eliminación documental (Acuerdo AGN 004/2019, M08-10).
     *
     * Exige: expediente cerrado o transferido, disposición final de eliminación o selección en la
     * TRD, tiempo de retención (gestión + central) cumplido desde el cierre, sin préstamos activos
     * ni eliminación previa, y un aprobador distinto de quien registra con permiso de aprobar.
     * El expediente queda en estado 'eliminado'.
     */
    public function storeEliminacion(Request $request)
    {
        $request->validate([
            'expediente_id' => 'required|exists:ofi_archivo_expedientes,id',
            'metodo' => 'required|in:destruccion_fisica,borrado_seguro',
            'responsable_ids' => 'required|array|min:2',
            'responsable_ids.*' => 'integer|distinct|exists:users,id',
            'testigos' => 'nullable|string|max:500',
            'aprobado_por_id' => 'required|exists:users,id',
        ]);

        if ((int) $request->aprobado_por_id === (int) Auth::id()) {
            return $this->warningResponse('El aprobador de la eliminación debe ser distinto de quien la registra');
        }

        $aprobador = User::find($request->aprobado_por_id);
        if ((int) $aprobador->estado !== 1 || ! $aprobador->can(self::PERMISO_APROBAR_ELIMINACION)) {
            return $this->warningResponse('El aprobador no está activo o no tiene permiso para aprobar eliminaciones');
        }

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::lockForUpdate()->findOrFail($request->expediente_id);

            if ($error = $this->validarEliminacion($expediente)) {
                DB::rollBack();

                return $this->warningResponse($error);
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

            $expediente->update(['estado' => 'eliminado']);

            DB::commit();

            return $this->createdResponse(
                $eliminacion->load(['expediente', 'aprobadoPor']),
                'Eliminación registrada exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->serverErrorResponse('Error al registrar la eliminación', $e->getMessage());
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

    // ==================== REGLAS TRD (AGN 004/2019) ====================

    /**
     * Precondiciones de una transferencia; null si procede o el motivo del rechazo.
     */
    private function validarTransferencia(OfiArchivoExpediente $expediente, string $tipo): ?string
    {
        if (OfiArchivoTransferencia::where('expediente_id', $expediente->id)->where('estado', 'pendiente')->exists()) {
            return 'El expediente ya tiene una transferencia pendiente';
        }

        $trd = $this->trdDelExpediente($expediente);
        if (! $trd) {
            return 'El expediente no tiene serie/subserie TRD asociada';
        }

        if ($tipo === 'primaria') {
            if ($expediente->estado !== 'cerrado' || $expediente->fase !== 'gestion') {
                return 'Solo se transfieren al archivo central expedientes cerrados en el archivo de gestión';
            }

            return $this->validarRetencion($expediente, (int) $trd->a_g, 'en el archivo de gestión');
        }

        if ($expediente->estado !== 'cerrado' || $expediente->fase !== 'central') {
            return 'La transferencia secundaria exige que el expediente esté cerrado en el archivo central';
        }
        if (! $trd->ct && ! $trd->s) {
            return 'La disposición final de la TRD no es conservación total ni selección';
        }

        return $this->validarRetencion($expediente, (int) $trd->a_g + (int) $trd->a_c, 'total (gestión + central)');
    }

    /**
     * Precondiciones de una eliminación; null si procede o el motivo del rechazo.
     */
    private function validarEliminacion(OfiArchivoExpediente $expediente): ?string
    {
        if (! in_array($expediente->estado, ['cerrado', 'transferido'], true)) {
            return 'Solo se pueden eliminar expedientes cerrados o transferidos';
        }
        if (OfiArchivoEliminacion::where('expediente_id', $expediente->id)->exists()) {
            return 'El expediente ya tiene una eliminación registrada';
        }
        if (OfiArchivoPrestamo::where('expediente_id', $expediente->id)->whereIn('estado', ['prestado', 'vencido'])->exists()) {
            return 'El expediente tiene préstamos sin devolver';
        }

        $trd = $this->trdDelExpediente($expediente);
        if (! $trd) {
            return 'El expediente no tiene serie/subserie TRD asociada';
        }
        if (! $trd->e && ! $trd->s) {
            return 'La disposición final de la TRD no permite eliminación (requiere E o S)';
        }

        return $this->validarRetencion($expediente, (int) $trd->a_g + (int) $trd->a_c, 'total (gestión + central)');
    }

    /**
     * B14: la instantánea de la TRD tomada al cierre; sin ella, la subserie o, si no hay, la serie.
     */
    private function trdDelExpediente(OfiArchivoExpediente $expediente): ?ClasificacionDocumentalTRD
    {
        return $expediente->trdRetencion();
    }

    private function validarRetencion(OfiArchivoExpediente $expediente, int $anios, string $etapa): ?string
    {
        if (! $expediente->fecha_cierre) {
            return 'El expediente no tiene fecha de cierre';
        }

        $cumple = Carbon::parse($expediente->fecha_cierre)->addYears($anios);
        if ($cumple->isFuture()) {
            return "No se ha cumplido el tiempo de retención {$etapa} ({$anios} años desde el cierre; se cumple el {$cumple->format('Y-m-d')})";
        }

        return null;
    }
}
