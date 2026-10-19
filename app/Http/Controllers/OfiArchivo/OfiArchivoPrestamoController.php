<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use App\Services\OfiArchivo\PrestamosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Préstamo y consulta de expedientes (diseño de expedientes §3.10): solicitud, aprobación del
 * responsable del archivo de la fase, entrega y devolución con folios. Las reglas están en
 * PrestamosService; cada fila lleva las acciones que el usuario puede hacer.
 */
class OfiArchivoPrestamoController extends Controller
{
    use ApiResponseTrait;

    private const RELACIONES = [
        'expediente:id,numero_expediente,nombre_expediente,fase,soporte,nivel_acceso,dependencia_id,dependencia_nom,estado',
        'tomo:id,numero',
        'solicitante:id,nombres,apellidos',
        'aprobador:id,nombres,apellidos',
        'entregadoPor:id,nombres,apellidos',
        'recibidoPor:id,nombres,apellidos',
    ];

    /**
     * El servicio recuerda los permisos del usuario; la instancia del controlador queda guardada en
     * la ruta, así que se crea uno por petición para que no se arrastren entre peticiones.
     */
    private ?PrestamosService $servicio = null;

    private ?Request $peticion = null;

    private function prestamos(): PrestamosService
    {
        if (! $this->servicio || $this->peticion !== request()) {
            $this->servicio = app(PrestamosService::class);
            $this->peticion = request();
        }

        return $this->servicio;
    }

    /**
     * Préstamos paginados. bandeja=mios (los que solicité), por_atender (por aprobar, entregar o
     * recibir en mi archivo) o todos, que exige el permiso Ver; sin él se ven solo los propios.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'bandeja' => 'nullable|in:mios,por_atender,todos',
            'estado' => 'nullable|string|max:20',
            'modalidad' => 'nullable|in:'.implode(',', OfiArchivoPrestamo::MODALIDADES),
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $user = $request->user();
        $verTodos = PrestamosService::tiene($user, PrestamosService::PERMISO_VER);
        $bandeja = $filtros['bandeja'] ?? ($verTodos ? 'todos' : 'mios');
        if ($bandeja === 'todos' && ! $verTodos) {
            return $this->forbiddenResponse('No tiene permiso para ver todos los préstamos');
        }

        $query = OfiArchivoPrestamo::with(self::RELACIONES)
            ->when($bandeja === 'mios', fn ($q) => $q->where('solicitante_id', $user->id))
            ->when($bandeja === 'por_atender', fn ($q) => $this->prestamos()->porAtender($q, $user))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['modalidad'] ?? null, fn ($q, $modalidad) => $q->where('modalidad', $modalidad))
            ->when($filtros['search'] ?? null, fn ($q, $search) => $q->whereHas('expediente', fn ($q) => $q
                ->where('numero_expediente', 'like', "%{$search}%")
                ->orWhere('nombre_expediente', 'like', "%{$search}%")));

        $pagina = $query->latest()->orderByDesc('id')->paginate($filtros['per_page'] ?? 15)
            ->through(fn (OfiArchivoPrestamo $p) => $this->conAcciones($p, $user));

        return $this->successResponse($pagina, 'Listado de préstamos obtenido');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $prestamo = OfiArchivoPrestamo::with(self::RELACIONES)->findOrFail($id);
        $user = $request->user();
        $acciones = $this->prestamos()->acciones($prestamo, $user);
        $propio = (int) $prestamo->solicitante_id === (int) $user->id;

        if (! $propio && ! PrestamosService::tiene($user, PrestamosService::PERMISO_VER) && ! in_array(true, $acciones, true)) {
            return $this->notFoundResponse('Préstamo no encontrado');
        }

        return $this->successResponse($this->conAcciones($prestamo, $user), 'Préstamo obtenido exitosamente');
    }

    /** Solicitud de préstamo; quien la hace es el solicitante. */
    public function store(Request $request): JsonResponse
    {
        if (! $this->prestamos()->puedeSolicitar($request->user())) {
            return $this->forbiddenResponse('No tiene permiso para solicitar préstamos');
        }

        $datos = $request->validate([
            'expediente_id' => 'required|integer|exists:ofi_archivo_expedientes,id',
            'tomo_id' => 'nullable|integer',
            'modalidad' => 'required|in:'.implode(',', OfiArchivoPrestamo::MODALIDADES),
            'motivo' => 'required|string|min:5|max:500',
            'fecha_devolucion_esperada' => 'required|date',
            'dependencia_destino' => 'nullable|string|max:200',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $prestamo = $this->prestamos()->solicitar($datos, $request->user());

        return $this->createdResponse($this->cargar($prestamo, $request), 'Solicitud de préstamo registrada');
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'fecha_devolucion_esperada' => 'nullable|date',
            'fundamento_autorizacion' => 'nullable|string|max:500',
            'version_publica' => 'nullable|boolean',
        ]);

        $prestamo = $this->prestamos()->aprobar(OfiArchivoPrestamo::findOrFail($id), $datos, $request->user());

        return $this->successResponse($this->cargar($prestamo, $request), 'Préstamo aprobado');
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => 'required|string|min:5|max:500']);

        $prestamo = $this->prestamos()->rechazar(OfiArchivoPrestamo::findOrFail($id), $datos['motivo'], $request->user());

        return $this->successResponse($this->cargar($prestamo, $request), 'Solicitud rechazada');
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $prestamo = $this->prestamos()->cancelar(OfiArchivoPrestamo::findOrFail($id), $request->user());

        return $this->successResponse($this->cargar($prestamo, $request), 'Solicitud cancelada');
    }

    public function entregar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['folios' => 'required|integer|min:1|max:100000']);

        $prestamo = $this->prestamos()->entregar(OfiArchivoPrestamo::findOrFail($id), (int) $datos['folios'], $request->user());

        return $this->successResponse($this->cargar($prestamo, $request), 'Entrega registrada');
    }

    public function devolver(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['folios' => 'nullable|integer|min:0|max:100000']);
        $folios = isset($datos['folios']) ? (int) $datos['folios'] : null;

        $prestamo = $this->prestamos()->devolver(OfiArchivoPrestamo::findOrFail($id), $folios, $request->user());

        return $this->successResponse(
            $this->cargar($prestamo, $request),
            $prestamo->folios_no_coinciden
                ? "Devolución registrada. Los folios no coinciden: se entregaron {$prestamo->folios_entrega} y se devolvieron {$prestamo->folios_devolucion}"
                : 'Devolución registrada exitosamente'
        );
    }

    /**
     * Expedientes que se pueden solicitar, con sus tomos y si están fuera del archivo. Solo datos
     * de identificación: el contenido se ve al aprobarse el préstamo.
     */
    public function expedientes(Request $request): JsonResponse
    {
        if (! $this->prestamos()->puedeSolicitar($request->user())) {
            return $this->forbiddenResponse('No tiene permiso para solicitar préstamos');
        }
        $search = $request->validate(['search' => 'nullable|string|max:100'])['search'] ?? null;

        $expedientes = OfiArchivoExpediente::query()
            ->whereIn('estado', PrestamosService::ESTADOS_PRESTABLES)
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('numero_expediente', 'like', "%{$search}%")
                ->orWhere('nombre_expediente', 'like', "%{$search}%")))
            ->with('tomos:id,expediente_id,numero')
            ->withExists(['prestamos as prestado' => fn ($q) => $q->fueraDelArchivo()->whereNull('tomo_id')])
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'numero_expediente', 'nombre_expediente', 'dependencia_nom', 'fase', 'soporte', 'nivel_acceso', 'estado']);

        return $this->successResponse([
            'plazo_maximo_dias' => ConfiguracionArchivo::prestamoPlazoMaximo(),
            'expedientes' => $expedientes,
        ], 'Expedientes disponibles para préstamo');
    }

    /**
     * Estadísticas rápidas de préstamos para dashboard.
     * Retorna: activos, vencidos, devueltos_hoy, prestados_hoy y solicitudes por aprobar.
     */
    public function estadisticas(): JsonResponse
    {
        return $this->successResponse([
            'activos' => OfiArchivoPrestamo::activos()->count(),
            'vencidos' => OfiArchivoPrestamo::vencidos()->count(),
            'devueltos_hoy' => OfiArchivoPrestamo::where('estado', 'devuelto')->whereDate('fecha_devolucion_real', today())->count(),
            'prestados_hoy' => OfiArchivoPrestamo::whereDate('fecha_prestamo', today())->count(),
            'solicitados' => OfiArchivoPrestamo::where('estado', 'solicitado')->count(),
        ], 'Estadísticas de préstamos obtenidas');
    }

    private function cargar(OfiArchivoPrestamo $prestamo, Request $request): array
    {
        return $this->conAcciones($prestamo->fresh(self::RELACIONES), $request->user());
    }

    private function conAcciones(OfiArchivoPrestamo $prestamo, $user): array
    {
        return $prestamo->toArray() + ['acciones' => $this->prestamos()->acciones($prestamo, $user)];
    }
}
