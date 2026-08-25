<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Helpers\MailConfigHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Pqrs\ListPqrsRequest;
use App\Http\Requests\Ventanilla\Pqrs\StorePqrsRequest;
use App\Http\Requests\Ventanilla\Pqrs\UpdatePqrsRequest;
use App\Http\Resources\VentanillaUnica\PqrsCollection;
use App\Http\Resources\VentanillaUnica\PqrsResource;
use App\Http\Traits\ApiResponseTrait;
use App\Mail\PqrsNotificacionEmail;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrs;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciHistorialClasificacionDocumental;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaHistorialNotificacion;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsOptimizedView;
use App\Services\ReportesExportService;
use App\Services\VentanillaUnica\PqrsService;
use App\Traits\AuditViewTrait;
use App\Traits\VentanillaAuditTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Controller VentanillaPqrsController â€” CRUD y operaciones PQRS
 *
 * Controlador principal del mÃ³dulo PQRS. Maneja:
 * - CRUD completo con paginaciÃ³n y filtros
 * - Cambio de estados con transiciones vÃ¡lidas
 * - PrÃ³rrogas de vencimiento
 * - ActualizaciÃ³n parcial de asunto, fechas, clasificaciÃ³n
 * - EliminaciÃ³n masiva
 * - ImpresiÃ³n de rÃ³tulo
 * - NotificaciÃ³n por email
 * - Firma digital con flujo OTP (solicitar â†’ validar â†’ guardar)
 * - AnulaciÃ³n de PQRS
 * - EstadÃ­sticas y lÃ­nea de tiempo
 * - Mis radicados (responsables del usuario)
 * - GestiÃ³n de estados disponibles
 * - ExportaciÃ³n de datos
 *
 * Permisos requeridos (prefijo 'Radicar -> PQRSF -> '):
 * - Listar: index, estadisticas, lineaTiempo, estadosDisponibles, transicionesEstado, misRadicados
 * - Crear: store
 * - Editar: update, cambiarEstado, aplicarProrroga, updateAsunto, updateFechas, updateClasificacion, bulkDestroy
 * - Mostrar: show, lineaTiempo
 * - Eliminar: destroy, bulkDestroy
 * - Imprimir Rotulo: imprimirRotulo
 * - Notificar Email: notificarEmail
 * - Firmar peticionario: solicitarOtpFirma, validarOtpFirma, guardarFirma
 * - Anular: anular
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */

class VentanillaPqrsController extends Controller
{
    use ApiResponseTrait, AuditViewTrait, VentanillaAuditTrait;

    private const PERM = 'Radicar -> PQRSF -> ';

    protected ReportesExportService $exportService;

    public function __construct(
        private PqrsService $pqrsService,
        ReportesExportService $exportService
    ) {
        $this->exportService = $exportService;
        $this->middleware('can:'.self::PERM.'Listar')->only(['index', 'estadisticas', 'lineaTiempo', 'estadosDisponibles', 'transicionesEstado', 'misRadicados', 'export', 'catalogos']);
        $this->middleware('can:'.self::PERM.'Crear')->only(['store']);
        $this->middleware('can:'.self::PERM.'Editar')->only(['update', 'cambiarEstado', 'aplicarProrroga', 'bulkDestroy']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['show', 'lineaTiempo']);
        $this->middleware('can:'.self::PERM.'Eliminar')->only(['destroy', 'bulkDestroy']);
        $this->middleware('can:'.self::PERM.'Actualizar asunto')->only(['updateAsunto']);
        $this->middleware('can:'.self::PERM.'Atualizar fechas de radicados')->only(['updateFechas']);
        $this->middleware('can:'.self::PERM.'Actualizar clasificacion de radicados')->only(['updateClasificacion']);
        $this->middleware('can:'.self::PERM.'Imprimir Rotulo')->only(['imprimirRotulo']);
        $this->middleware('can:'.self::PERM.'Notificar Email')->only(['notificarEmail']);
        $this->middleware('can:'.self::PERM.'Firmar peticionario')->only(['solicitarOtpFirma', 'validarOtpFirma', 'guardarFirma']);
        $this->middleware('can:'.self::PERM.'Anular')->only(['anular']);
    }

    public function index(ListPqrsRequest $request): JsonResponse
    {
        try {
            // Toda PQRS nace de un radicado recibido; el filtro descarta huÃ©rfanas.
            $query = VentanillaPqrsOptimizedView::query()
                ->conPermisoJerarquico(auth()->user())
                ->whereNotNull('ventanilla_radica_reci_id');

            // Apply additional filters using view model scopes
            $query->search($request->search)
                ->tipoPqrs($request->tipo_pqrs_id)
                ->estadoTramite($request->estado_tramite)
                ->prioridad($request->prioridad)
                ->clasificacionDocumental($request->clasificacion_id)
                ->tercero($request->gestion_tercero_id)
                ->fechaEntre($request->fecha_desde, $request->fecha_hasta)
                ->ordenadoPorFecha();

            $perPage = $request->get('per_page', 15);
            $pqrs = $query->paginate($perPage);

            // Hydrate full PQRS models for the returned page IDs to get relationships
            $ids = $pqrs->getCollection()->pluck('id')->toArray();
            $pqrsCompletos = VentanillaPqrs::whereIn('id', $ids)
                ->with([
                    'radicado.tercero',
                    'radicado.clasificacionDocumental',
                    'radicado.responsables.userCargo.user',
                    'radicado.responsables.userCargo.cargo',
                    'tercero',
                    'tipoPqrs',
                    'clasificacionDocumental',
                ])
                ->get()
                ->keyBy('id');

            $pqrs->getCollection()->transform(function ($item) use ($pqrsCompletos) {
                $pqrsCompleto = $pqrsCompletos->get($item->id);
                if ($pqrsCompleto) {
                    $item->dias_habiles_restantes = $pqrsCompleto->getDiasHabilesRestantes();
                    $item->estado_color = $pqrsCompleto->getEstadoColor();
                    $item->radicado = $pqrsCompleto->radicado;
                    $item->tercero = $pqrsCompleto->tercero;
                    $item->tipoPqrs = $pqrsCompleto->tipoPqrs;
                    $item->clasificacionDocumental = $pqrsCompleto->clasificacionDocumental;
                }
                return $item;
            });

            return $this->successResponse($pqrs, 'Listado de PQRS obtenido exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el listado de PQRS', $e->getMessage(), 500);
        }
    }

    /**
     * Exporta el listado de PQRS en el formato solicitado.
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
        ]);

        $query = VentanillaPqrs::with([
            'radicado',
            'tercero',
            'tipoPqrs',
            'clasificacionDocumental',
        ])->whereNotNull('ventanilla_radica_reci_id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('radicado', function ($q) use ($request) {
                    $q->where('num_radicado', 'like', "%{$request->search}%")
                        ->orWhere('asunto', 'like', "%{$request->search}%");
                })->orWhere('nom_afectado', 'like', "%{$request->search}%")
                    ->orWhere('num_docu_afectado', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('tipo_pqrs_id')) {
            $query->where('tipo_pqrs_id', $request->tipo_pqrs_id);
        }

        if ($request->filled('estado_tramite')) {
            $query->where('estado_tramite', $request->estado_tramite);
        }

        if ($request->filled('prioridad')) {
            $query->where('prioridad', $request->prioridad);
        }

        if ($request->filled('clasificacion_id')) {
            $query->where('clasificacion_documental_trd_id', $request->clasificacion_id);
        }

        if ($request->filled('gestion_tercero_id')) {
            $query->where('gestion_tercero_id', $request->gestion_tercero_id);
        }

        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereBetween('fecha_vencimiento', [$request->fecha_desde, $request->fecha_hasta]);
        }

        $pqrsList = $query->latest()->get();

        $datos = $pqrsList->map(function ($p) {
            return [
                'id' => $p->id,
                'num_radicado' => $p->radicado?->num_radicado ?? '',
                'tipo_pqrs' => $p->tipoPqrs?->nombre ?? '',
                'estado_tramite' => $p->estado_tramite ?? '',
                'prioridad' => $p->prioridad ?? '',
                'nom_afectado' => $p->nom_afectado ?? '',
                'num_docu_afectado' => $p->num_docu_afectado ?? '',
                'fecha_vencimiento' => $p->fecha_vencimiento?->format('Y-m-d') ?? '',
                'clasificacion' => $p->clasificacionDocumental?->nom ?? '',
                'created_at' => $p->created_at,
            ];
        })->toArray();

        $data = [
            'titulo' => 'PQRS',
            'datos' => $datos,
        ];

        $nombre = 'pqrs';

        return match ($request->format) {
            'excel' => $this->downloadExcel($data, $nombre),
            'pdf' => $this->downloadPDF($data, $nombre),
            'csv' => $this->exportService->exportarCSV($data, $nombre),
        };
    }

    protected function downloadExcel(array $data, string $nombre)
    {
        $path = $this->exportService->exportarExcel($data, $nombre);

        return response()->download($path, $nombre . '.xlsx')->deleteFileAfterSend(true);
    }

    protected function downloadPDF(array $data, string $nombre)
    {
        $path = $this->exportService->exportarPDF($data, $nombre);

        return response()->download($path, $nombre . '.pdf')->deleteFileAfterSend(true);
    }

    public function store(StorePqrsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            \Log::info('DEBUG PQRS Store - datos validados', [
                'ventanilla_radica_reci_id' => $validated['ventanilla_radica_reci_id'] ?? 'NOT_SET',
                'tipo_pqrs_id' => $validated['tipo_pqrs_id'] ?? 'NOT_SET',
                'prioridad' => $validated['prioridad'] ?? 'NOT_SET',
                'detalle_solicitud' => $validated['detalle_solicitud'] ?? 'NOT_SET',
                'gestion_tercero_id' => $validated['gestion_tercero_id'] ?? 'NOT_SET',
            ]);

            if (! empty($validated['ventanilla_radica_reci_id'])) {
                $pqrs = $this->pqrsService->crearDesdeRadicado(
                    $validated['ventanilla_radica_reci_id'],
                    $validated
                );
            } else {
                $pqrs = $this->pqrsService->crearIndependiente($validated);
            }

            $this->auditVentanilla($pqrs, 'created', $pqrs->radicado?->num_radicado ?? 'PQRS #'.$pqrs->id);

            return $this->successResponse(
                new PqrsResource($pqrs),
                'PQRS creada exitosamente. Vence: '.$pqrs->fecha_vencimiento->format('Y-m-d'),
                201
            );
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            \Log::error('ERROR PQRS Store', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'token']),
            ]);

            return $this->errorResponse('Error al crear la PQRS', $e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::with([
                'radicado.tercero',
                'radicado.clasificacionDocumental',
                'radicado.archivos',
                'radicado.responsables.userCargo.user',
                'radicado.responsables.userCargo.cargo',
                'tercero',
                'tipoPqrs',
                'clasificacionDocumental',
                'divisionPoliticaAfectado',
            ])->find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $pqrs->dias_habiles_restantes = $pqrs->getDiasHabilesRestantes();

            return $this->successResponse(new PqrsResource($pqrs), 'Detalle de PQRS');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener la PQRS', $e->getMessage(), 500);
        }
    }

    public function update(UpdatePqrsRequest $request, int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $validated = $request->validated();

            $pqrs->update($validated);

            $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id);

            return $this->successResponse(new PqrsResource($pqrs), 'PQRS actualizada exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar la PQRS', $e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $numRadicado = $pqrs->radicado?->num_radicado ?? $pqrs->id;

            $pqrs->delete();

            $this->auditVentanilla($pqrs, 'deleted', $numRadicado);

            return $this->successResponse(null, 'PQRS eliminada exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar la PQRS', $e->getMessage(), 500);
        }
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'estado_tramite' => 'required|in:Pendiente,En Tramite,Respondida,Vencida',
                'fecha_respuesta' => 'nullable|date',
            ]);

            $pqrs = $this->pqrsService->cambiarEstado($id, $validated['estado_tramite'], $validated['fecha_respuesta'] ?? null);

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'estado' => $validated['estado_tramite'],
            ]);

            return $this->successResponse(new PqrsResource($pqrs), 'Estado actualizado a: '.$validated['estado_tramite']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('PQRS no encontrada', null, 404);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al cambiar el estado', $e->getMessage(), 500);
        }
    }

    public function aplicarProrroga(int $id): JsonResponse
    {
        try {
            $pqrs = $this->pqrsService->aplicarProrroga($id);

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'prorroga' => true,
            ]);

            return $this->successResponse(
                new PqrsResource($pqrs),
                'PrÃ³rroga aplicada. Nuevo vencimiento: '.$pqrs->fecha_vencimiento->format('Y-m-d')
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('PQRS no encontrada', null, 404);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al aplicar la prÃ³rroga', $e->getMessage(), 500);
        }
    }

    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = $this->pqrsService->getEstadisticas();

            return $this->successResponse($estadisticas, 'EstadÃ­sticas de PQRS');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener las estadÃ­sticas', $e->getMessage(), 500);
        }
    }

    public function lineaTiempo(int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::with([
                'radicado',
                'radicado.usuarioCreaRadicado',
                'radicado.usuarioSubio',
                'radicado.responsables.userCargo.user',
                'radicado.responsables.userCargo.cargo',
                'radicado.archivos.usuarioSubido',
                'tercero',
                'tipoPqrs',
            ])->find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $eventos = [];

            // â”€â”€ Eventos del radicado recibido asociado â”€â”€
            $radicado = $pqrs->radicado;
            if ($radicado) {
                // Radicado creado
                $eventos[] = [
                    'fecha' => $radicado->created_at->toIso8601String(),
                    'tipo' => 'radicado_creado',
                    'titulo' => 'Radicado creado',
                    'descripcion' => 'Se creÃ³ el radicado '.$radicado->num_radicado,
                    'datos' => [
                        'num_radicado' => $radicado->num_radicado,
                        'radicado_id' => $radicado->id,
                    ],
                ];

                // Archivo digital subido
                if (! empty($radicado->archivo_digital)) {
                    $eventos[] = [
                        'fecha' => $radicado->updated_at->toIso8601String(),
                        'tipo' => 'archivo_digital_subido',
                        'titulo' => 'Archivo digital subido',
                        'descripcion' => 'Se cargÃ³ el archivo digital principal: '.basename($radicado->archivo_digital),
                        'usuario' => $radicado->usuarioSubio ? $radicado->usuarioSubio->getInfoUsuario() : null,
                        'datos' => [
                            'archivo_nombre' => basename($radicado->archivo_digital),
                            'extension' => pathinfo($radicado->archivo_digital, PATHINFO_EXTENSION),
                        ],
                    ];
                }

                // Responsables asignados
                foreach ($radicado->responsables as $responsable) {
                    $user = $responsable->userCargo && $responsable->userCargo->user
                        ? $responsable->userCargo->user->getInfoUsuario()
                        : null;
                    $cargoRel = $responsable->userCargo?->cargo;
                    $cargo = (isset($cargoRel) && is_object($cargoRel)) ? $cargoRel : null;
                    $eventos[] = [
                        'fecha' => $responsable->created_at->toIso8601String(),
                        'tipo' => 'responsable_asignado',
                        'titulo' => 'Responsable asignado',
                        'descripcion' => $cargo
                            ? 'Se asignÃ³ como responsable'.($responsable->custodio ? ' (custodio)' : '').': '.$cargo->nom_organico
                            : 'Se asignÃ³ un responsable',
                        'usuario' => $user,
                        'datos' => [
                            'responsable_id' => $responsable->id,
                        ],
                    ];
                }

                // Archivos adjuntos subidos
                foreach ($radicado->archivos as $archivo) {
                    $eventos[] = [
                        'fecha' => $archivo->created_at->toIso8601String(),
                        'tipo' => 'adjunto_subido',
                        'titulo' => 'Archivo adjunto subido',
                        'descripcion' => 'Se subiÃ³ el archivo: '.$archivo->nom_origi,
                        'usuario' => $archivo->usuarioSubido ? $archivo->usuarioSubido->getInfoUsuario() : null,
                        'datos' => [
                            'nombre' => $archivo->nom_origi,
                            'tipo' => $archivo->archivo_tipo,
                        ],
                    ];
                }
            }

            // â”€â”€ Eventos propios del PQRS â”€â”€
            $eventos[] = [
                'fecha' => $pqrs->created_at->toIso8601String(),
                'tipo' => 'pqrs_creada',
                'titulo' => 'PQRS creada',
                'descripcion' => 'Se creÃ³ la PQRS tipo '.($pqrs->tipoPqrs?->nombre ?? 'desconocido'),
                'datos' => [
                    'id' => $pqrs->id,
                    'estado_tramite' => $pqrs->estado_tramite,
                    'prioridad' => $pqrs->prioridad,
                    'fecha_vencimiento' => $pqrs->fecha_vencimiento?->format('Y-m-d'),
                ],
            ];

            if ($pqrs->estado_tramite === 'Respondida' && $pqrs->fecha_respuesta) {
                $eventos[] = [
                    'fecha' => $pqrs->fecha_respuesta->toIso8601String(),
                    'tipo' => 'pqrs_respondida',
                    'titulo' => 'PQRS respondida',
                    'descripcion' => 'Se registrÃ³ respuesta a la PQRS',
                    'datos' => [
                        'fecha_respuesta' => $pqrs->fecha_respuesta->format('Y-m-d H:i:s'),
                    ],
                ];
            }

            if ($pqrs->tiene_prorroga) {
                $eventos[] = [
                    'fecha' => $pqrs->updated_at->toIso8601String(),
                    'tipo' => 'prorroga_aplicada',
                    'titulo' => 'PrÃ³rroga aplicada',
                    'descripcion' => 'Se extendiÃ³ la fecha de vencimiento',
                    'datos' => [
                        'nueva_fecha_vencimiento' => $pqrs->fecha_vencimiento?->format('Y-m-d'),
                    ],
                ];
            }

            usort($eventos, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']));

            return $this->successResponse([
                'pqrs' => [
                    'id' => $pqrs->id,
                    'num_radicado' => $pqrs->radicado?->num_radicado,
                    'tipo_pqrs' => $pqrs->tipoPqrs?->nombre,
                    'estado_tramite' => $pqrs->estado_tramite,
                    'created_at' => $pqrs->created_at->toIso8601String(),
                ],
                'total_eventos' => count($eventos),
                'eventos' => $eventos,
            ], 'LÃ­nea de tiempo de PQRS');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener la lÃ­nea de tiempo', $e->getMessage(), 500);
        }
    }

    public function updateAsunto(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'detalle_solicitud' => 'required|string|max:3000',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $pqrs->update(['detalle_solicitud' => $request->detalle_solicitud]);

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'campo' => 'detalle_solicitud',
            ]);

            return $this->successResponse(new PqrsResource($pqrs), 'Detalle de solicitud actualizado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar el detalle', $e->getMessage(), 500);
        }
    }

    public function updateFechas(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'fechor_tramite' => 'required|date',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $pqrs->update([
                'fechor_tramite' => $request->fechor_tramite,
                'fecha_vencimiento' => $pqrs->calcularFechaVencimiento(),
            ]);

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'campo' => 'fechor_tramite',
            ]);

            return $this->successResponse(new PqrsResource($pqrs), 'Fechas actualizadas exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar las fechas', $e->getMessage(), 500);
        }
    }

    public function updateClasificacion(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'clasificacion_documental_trd_id' => 'required|exists:clasificacion_documental_trd,id',
                'motivo' => 'required|string|max:500',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $clasificacionAnteriorId = $pqrs->clasificacion_documental_trd_id;
            $clasificacionNuevaId = $request->clasificacion_documental_trd_id;

            $pqrs->update(['clasificacion_documental_trd_id' => $clasificacionNuevaId]);

            // Registrar en historial de clasificaciÃ³n del radicado (fuente Ãºnica)
            VentanillaRadicaReciHistorialClasificacionDocumental::create([
                'radica_reci_id' => $pqrs->ventanilla_radica_reci_id,
                'clasificacion_anterior_id' => $clasificacionAnteriorId,
                'clasificacion_nueva_id' => $clasificacionNuevaId,
                'motivo' => $request->motivo,
                'user_id' => auth()->id(),
            ]);

            $pqrs->load('clasificacionDocumental');

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'campo' => 'clasificacion_documental_trd_id',
                'clasificacion_anterior_id' => $clasificacionAnteriorId,
                'clasificacion_nueva_id' => $clasificacionNuevaId,
            ]);

            return $this->successResponse(new PqrsResource($pqrs), 'ClasificaciÃ³n actualizada exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar la clasificaciÃ³n', $e->getMessage(), 500);
        }
    }

    public function imprimirRotulo(int $id): Response|JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::with(['radicado.tercero'])->find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $radicado = $pqrs->radicado;

            $data = [
                'entidad' => config('app.name', 'Entidad'),
                'nit' => config('app.nit', 'N/A'),
                'fecha' => now()->format('Y-m-d'),
                'hora' => now()->format('H:i:s'),
                'radicado' => [
                    'num_radicado' => $radicado?->num_radicado ?? 'N/A',
                    'fec_radi' => $radicado?->fec_radi ?? now()->format('Y-m-d'),
                    'hor_radi' => $radicado?->hor_radi ?? now()->format('H:i:s'),
                    'tercero' => [
                        'nom_razo_soci' => $radicado?->tercero?->nom_razo_soci ?? $pqrs->nom_afectado ?? 'N/A',
                        'num_identific' => $radicado?->tercero?->num_identific ?? $pqrs->num_docu_afectado ?? 'N/A',
                    ],
                    'num_folios' => $radicado?->num_folios ?? '0',
                    'num_anexos' => $radicado?->num_anexos ?? '0',
                    'codigo_verificacion' => $radicado?->codigo_verificacion ?? $pqrs->id,
                ],
            ];

            $pdf = Pdf::loadView('pdf.rotulo', $data);
            $pdf->setPaper([0, 0, 280, 420], 'portrait');

            return $pdf->stream('rotulo-'.($radicado?->num_radicado ?? $pqrs->id).'.pdf');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al generar el rÃ³tulo', $e->getMessage(), 500);
        }
    }

    public function notificarEmail(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'modo' => 'required|string|in:todos,responsables,remitente',
            ]);

            // El asunto y el cuerpo del correo los genera la plantilla del
            // Mailable (misma prÃ¡ctica que las notificaciones de radicados
            // recibidos/enviados/internos); la UI solo elige a quiÃ©n notificar.

            $pqrs = VentanillaPqrs::with(['radicado.tercero', 'tipoPqrs', 'responsables.userCargo.user'])->find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            // Destinatarios segÃºn el modo seleccionado en el modal
            $emails = [];

            if (in_array($validated['modo'], ['todos', 'responsables'], true)) {
                foreach ($pqrs->responsables as $resp) {
                    if ($resp->userCargo && $resp->userCargo->user && $resp->userCargo->user->email) {
                        $emails[] = $resp->userCargo->user->email;
                    }
                }
            }

            if (in_array($validated['modo'], ['todos', 'remitente'], true)) {
                $emailRemitente = $pqrs->radicado?->tercero?->email;

                if ($emailRemitente) {
                    $emails[] = $emailRemitente;
                }
            }

            $emails = array_values(array_unique(array_filter($emails)));

            if (empty($emails)) {
                return $this->errorResponse('No hay destinatarios vÃ¡lidos para enviar la notificaciÃ³n', null, 422);
            }

            // Configurar SMTP desde config_varias (paridad con recibidos/enviados/internos)
            MailConfigHelper::configureFromConfigVarias();
            if (! MailConfigHelper::isConfigured()) {
                Log::warning('SMTP no configurado', ['pqrs_id' => $id]);

                return $this->errorResponse('No se pudo enviar el correo. Verifique la configuraciÃ³n SMTP en Otras configuraciones â†’ Correo.', null, 500);
            }

            // Enviar email a todos los destinatarios usando Mailable
            Mail::to($emails)
                ->send(new PqrsNotificacionEmail($pqrs));

            // Registrar en historial de notificaciones del radicado (fuente Ãºnica)
            VentanillaRadicaHistorialNotificacion::create([
                'radicado_id' => $pqrs->ventanilla_radica_reci_id,
                'tipo' => $validated['modo'],
                'destinatarios' => $emails,
                'total_enviados' => count($emails),
                'user_id' => auth()->id(),
            ]);

            $this->auditVentanilla($pqrs, 'notified', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'modo' => $validated['modo'],
                'total_enviados' => count($emails),
            ]);

            return $this->successResponse(['total_enviados' => count($emails)], 'NotificaciÃ³n enviada exitosamente a '.count($emails).' destinatario(s)');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            Log::error('Error notificarEmail PQRS', ['pqrs_id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al enviar la notificaciÃ³n', $e->getMessage(), 500);
        }
    }

    /**
     * Solicita un cÃ³digo OTP para firmar una PQRS electrÃ³nicamente.
     */
    public function solicitarOtpFirma(int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            if ($pqrs->estado_firma === 'firmada') {
                return $this->errorResponse('Esta PQRS ya ha sido firmada', null, 422);
            }

            $user = Auth::user();

            $this->pqrsService->solicitarOtpFirma($user, $pqrs);

            return $this->successResponse(null, 'CÃ³digo OTP enviado al correo del usuario');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al solicitar el OTP', $e->getMessage(), 500);
        }
    }

    /**
     * Valida el cÃ³digo OTP para firmar una PQRS.
     */
    public function validarOtpFirma(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'otp' => 'required|string|size:6',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $user = Auth::user();

            $valido = $this->pqrsService->validarOtpFirma($user, $request->otp, $pqrs);

            if (! $valido) {
                return $this->errorResponse('CÃ³digo OTP invÃ¡lido o expirado', null, 403);
            }

            return $this->successResponse(['valido' => true], 'OTP validado correctamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al validar el OTP', $e->getMessage(), 500);
        }
    }

    /**
     * Guarda la firma electrÃ³nica de una PQRS.
     */
    public function guardarFirma(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'firma_digital' => 'required|string',
                'firmado_en_representacion' => 'nullable|boolean',
                'nombre_representado' => 'nullable|string|max:255',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            if ($pqrs->estado_firma === 'firmada') {
                return $this->errorResponse('Esta PQRS ya ha sido firmada', null, 422);
            }

            $user = Auth::user();

            $pqrs = $this->pqrsService->guardarFirma($pqrs, $request->all(), $user);

            $this->auditVentanilla($pqrs, 'signed', $pqrs->radicado?->num_radicado ?? $pqrs->id);

            return $this->successResponse(new PqrsResource($pqrs), 'PQRS firmada electrÃ³nicamente con Ã©xito');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al guardar la firma', $e->getMessage(), 500);
        }
    }

    /**
     * Anula una PQRS con motivo.
     */
    public function anular(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'motivo' => 'required|string|max:1000',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $pqrs = $this->pqrsService->anularPqrs($pqrs, $request->motivo);

            $this->auditVentanilla($pqrs, 'annulled', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'motivo' => $request->motivo,
            ]);

            return $this->successResponse(new PqrsResource($pqrs), 'PQRS anulada exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al anular la PQRS', $e->getMessage(), 500);
        }
    }

    /**
     * Lista PQRS con firma pendiente.
     */
    public function pendientesFirma(): JsonResponse
    {
        try {
            $pqrs = $this->pqrsService->pendientesFirma();

            return (new PqrsCollection($pqrs))->toResponse(request());
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener PQRS pendientes de firma', $e->getMessage(), 500);
        }
    }

    /**
     * Lista PQRS asignados al usuario autenticado (mis radicados).
     */
    public function misRadicados(ListPqrsRequest $request): JsonResponse
    {
        try {
            // PQRS asignadas al usuario: los responsables viven en el RADICADO RECIBIDO asociado
            $query = VentanillaPqrsOptimizedView::query()
                ->conPermisoJerarquico(auth()->user())
                ->whereHas('radicado.responsables', function ($q) {
                    $q->whereHas('userCargo', function ($q) {
                        $q->where('user_id', auth()->id());
                    });
                })
                ->search($request->search)
                ->tipoPqrs($request->tipo_pqrs_id)
                ->estadoTramite($request->estado_tramite)
                ->prioridad($request->prioridad)
                ->clasificacionDocumental($request->clasificacion_id)
                ->tercero($request->gestion_tercero_id)
                ->fechaEntre($request->fecha_desde, $request->fecha_hasta)
                ->ordenadoPorFecha();

            $perPage = $request->get('per_page', 15);
            $pqrs = $query->paginate($perPage);

            $ids = $pqrs->getCollection()->pluck('id')->toArray();
            $pqrsCompletos = VentanillaPqrs::whereIn('id', $ids)
                ->with([
                    'radicado.tercero',
                    'radicado.clasificacionDocumental',
                    'radicado.responsables.userCargo.user',
                    'radicado.responsables.userCargo.cargo',
                    'tercero',
                    'tipoPqrs',
                    'clasificacionDocumental',
                ])
                ->get()
                ->keyBy('id');

            $pqrs->getCollection()->transform(function ($item) use ($pqrsCompletos) {
                $pqrsCompleto = $pqrsCompletos->get($item->id);
                if ($pqrsCompleto) {
                    $item->dias_habiles_restantes = $pqrsCompleto->getDiasHabilesRestantes();
                    $item->estado_color = $pqrsCompleto->getEstadoColor();
                    $item->radicado = $pqrsCompleto->radicado;
                    $item->tercero = $pqrsCompleto->tercero;
                    $item->tipoPqrs = $pqrsCompleto->tipoPqrs;
                    $item->clasificacionDocumental = $pqrsCompleto->clasificacionDocumental;
                }
                return $item;
            });

            return $this->successResponse($pqrs, 'Mis PQRS asignados');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener mis PQRS', $e->getMessage(), 500);
        }
    }

    /**
     * CatÃ¡logos paramÃ©tricos del formulario PQRS.
     *
     * Resuelve las listas de config_listas POR NOMBRE (no por ID, que varÃ­a
     * entre entornos): Tipos de PQRS, Prioridad PQRS, Modalidad PQRS,
     * Tipos de RecepciÃ³n y Tipos de solicitud. Devuelve solo detalles activos.
     *
     * @return JsonResponse CatÃ¡logos agrupados por clave:
     *   tipos_pqrs, prioridades, modalidades, medios_recepcion, tipos_solicitud
     *
     * @author Jhon Javer Lozano Arce
     *
     * @date 2026-08-24
     */
    public function catalogos(): JsonResponse
    {
        try {
            $mapa = [
                'tipos_pqrs' => 'Tipos de PQRS',
                'prioridades' => 'Prioridad PQRS',
                'modalidades' => 'Modalidad PQRS',
                'medios_recepcion' => 'Tipos de RecepciÃ³n',
                'tipos_solicitud' => 'Tipos de solicitud',
            ];

            $catalogos = [];

            foreach ($mapa as $clave => $nombreLista) {
                $catalogos[$clave] = DB::table('config_listas_detalles as cld')
                    ->join('config_listas as cl', 'cld.lista_id', '=', 'cl.id')
                    ->where('cl.nombre', $nombreLista)
                    ->where('cld.estado', 1)
                    ->orderBy('cld.id')
                    ->get(['cld.id', 'cld.nombre', 'cld.codigo']);
            }

            return $this->successResponse($catalogos, 'CatÃ¡logos PQRS obtenidos exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener los catÃ¡logos PQRS', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los estados disponibles para el flujo de trabajo.
     */
    public function estadosDisponibles(): JsonResponse
    {
        try {
            $estados = [
                ['id' => 'Pendiente', 'nombre' => 'Pendiente'],
                ['id' => 'En Tramite', 'nombre' => 'En TrÃ¡mite'],
                ['id' => 'Respondida', 'nombre' => 'Respondida'],
                ['id' => 'Vencida', 'nombre' => 'Vencida'],
            ];

            return $this->successResponse($estados, 'Estados disponibles');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener los estados', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene las transiciones vÃ¡lidas desde un estado dado.
     */
    public function transicionesEstado(string $estadoActual): JsonResponse
    {
        try {
            $transiciones = match ($estadoActual) {
                'Pendiente' => ['En Tramite', 'Respondida', 'Vencida'],
                'En Tramite' => ['Respondida', 'Vencida', 'Pendiente'],
                'Respondida' => ['En Tramite'],
                'Vencida' => ['En Tramite', 'Respondida'],
                default => [],
            };

            return $this->successResponse($transiciones, 'Transiciones vÃ¡lidas');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener las transiciones', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina mÃºltiples PQRS en lote.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:ventanilla_pqrs,id',
            ]);

            $ids = $request->ids;

            DB::beginTransaction();
            try {
                $eliminados = [];
                foreach ($ids as $id) {
                    $pqrs = VentanillaPqrs::find($id);
                    if ($pqrs) {
                        $numRadicado = $pqrs->radicado?->num_radicado ?? $pqrs->id;
                        $pqrs->delete();
                        $eliminados[] = ['id' => $id, 'num_radicado' => $numRadicado];

                        $this->auditVentanilla($pqrs, 'deleted', $numRadicado, ['bulk' => true]);
                    }
                }
                DB::commit();

                return $this->successResponse($eliminados, count($eliminados).' PQRS eliminadas exitosamente');
            } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar las PQRS en lote', $e->getMessage(), 500);
        }
    }

    /**
     * Historial de notificaciones de una PQRS.
     */
    public function historialNotificaciones(int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $historial = VentanillaRadicaHistorialNotificacion::where('radicado_id', $pqrs->ventanilla_radica_reci_id)
                ->with('usuario')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de notificaciones');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el historial de notificaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Historial de cambios de clasificaciÃ³n de una PQRS.
     */
    public function historialClasificacion(int $id): JsonResponse
    {
        try {
            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $historial = VentanillaRadicaReciHistorialClasificacionDocumental::where('radica_reci_id', $pqrs->ventanilla_radica_reci_id)
                ->with(['clasificacionAnterior', 'clasificacionNueva', 'usuario'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de clasificaciÃ³n');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el historial de clasificaciÃ³n', $e->getMessage(), 500);
        }
    }
}

