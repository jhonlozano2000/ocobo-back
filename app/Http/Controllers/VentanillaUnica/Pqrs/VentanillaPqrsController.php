<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Pqrs\ListPqrsRequest;
use App\Http\Requests\Ventanilla\Pqrs\StorePqrsRequest;
use App\Http\Requests\Ventanilla\Pqrs\UpdatePqrsRequest;
use App\Http\Resources\VentanillaUnica\PqrsCollection;
use App\Http\Resources\VentanillaUnica\PqrsResource;
use App\Http\Traits\ApiResponseTrait;
use App\Mail\PqrsNotificacionEmail;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Services\VentanillaUnica\PqrsService;
use App\Traits\AuditViewTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class VentanillaPqrsController extends Controller
{
    use ApiResponseTrait, AuditViewTrait;

    private const PERM = 'Radicar -> PQRSF -> ';

    public function __construct(
        private PqrsService $pqrsService
    ) {
        $this->middleware('can:'.self::PERM.'Listar')->only(['index', 'estadisticas', 'lineaTiempo']);
        $this->middleware('can:'.self::PERM.'Crear')->only(['store']);
        $this->middleware('can:'.self::PERM.'Editar')->only(['update']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['show', 'lineaTiempo']);
        $this->middleware('can:'.self::PERM.'Eliminar')->only(['destroy']);
        $this->middleware('can:'.self::PERM.'Cambiar Estado')->only(['cambiarEstado']);
        $this->middleware('can:'.self::PERM.'Aplicar Prorroga')->only(['aplicarProrroga']);
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
            // Se filtran solo PQRS con radicado asociado (whereNotNull).
            // Las PQRS independientes (sin radicado) se gestionan por separado.
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

            $pqrs = $query->latest()->paginate($request->get('per_page', 15));

            $pqrs->getCollection()->transform(function ($item) {
                $item->dias_habiles_restantes = $item->getDiasHabilesRestantes();

                return $item;
            });

            return (new PqrsCollection($pqrs))->toResponse($request);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de PQRS', $e->getMessage(), 500);
        }
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
                'Prórroga aplicada. Nuevo vencimiento: '.$pqrs->fecha_vencimiento->format('Y-m-d')
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('PQRS no encontrada', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al aplicar la prórroga', $e->getMessage(), 500);
        }
    }

    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = $this->pqrsService->getEstadisticas();

            return $this->successResponse($estadisticas, 'Estadísticas de PQRS');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
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

            // ── Eventos del radicado recibido asociado ──
            $radicado = $pqrs->radicado;
            if ($radicado) {
                // Radicado creado
                $eventos[] = [
                    'fecha' => $radicado->created_at->toIso8601String(),
                    'tipo' => 'radicado_creado',
                    'titulo' => 'Radicado creado',
                    'descripcion' => 'Se creó el radicado '.$radicado->num_radicado,
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
                        'descripcion' => 'Se cargó el archivo digital principal: '.basename($radicado->archivo_digital),
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
                            ? 'Se asignó como responsable'.($responsable->custodio ? ' (custodio)' : '').': '.$cargo->nom_organico
                            : 'Se asignó un responsable',
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
                        'descripcion' => 'Se subió el archivo: '.$archivo->nom_origi,
                        'usuario' => $archivo->usuarioSubido ? $archivo->usuarioSubido->getInfoUsuario() : null,
                        'datos' => [
                            'nombre' => $archivo->nom_origi,
                            'tipo' => $archivo->archivo_tipo,
                        ],
                    ];
                }
            }

            // ── Eventos propios del PQRS ──
            $eventos[] = [
                'fecha' => $pqrs->created_at->toIso8601String(),
                'tipo' => 'pqrs_creada',
                'titulo' => 'PQRS creada',
                'descripcion' => 'Se creó la PQRS tipo '.($pqrs->tipoPqrs?->nombre ?? 'desconocido'),
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
                    'descripcion' => 'Se registró respuesta a la PQRS',
                    'datos' => [
                        'fecha_respuesta' => $pqrs->fecha_respuesta->format('Y-m-d H:i:s'),
                    ],
                ];
            }

            if ($pqrs->tiene_prorroga) {
                $eventos[] = [
                    'fecha' => $pqrs->updated_at->toIso8601String(),
                    'tipo' => 'prorroga_aplicada',
                    'titulo' => 'Prórroga aplicada',
                    'descripcion' => 'Se extendió la fecha de vencimiento',
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
            ], 'Línea de tiempo de PQRS');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la línea de tiempo', $e->getMessage(), 500);
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
            return $this->errorResponse('Error al actualizar las fechas', $e->getMessage(), 500);
        }
    }

    public function updateClasificacion(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'clasificacion_documental_trd_id' => 'required|exists:clasificacion_documental_trd,id',
            ]);

            $pqrs = VentanillaPqrs::find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $pqrs->update(['clasificacion_documental_trd_id' => $request->clasificacion_documental_trd_id]);

            $pqrs->load('clasificacionDocumental');

            $this->auditVentanilla($pqrs, 'updated', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'campo' => 'clasificacion_documental_trd_id',
            ]);

            return $this->successResponse(new PqrsResource($pqrs), 'Clasificación actualizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la clasificación', $e->getMessage(), 500);
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
            return $this->errorResponse('Error al generar el rótulo', $e->getMessage(), 500);
        }
    }

    public function notificarEmail(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'destinatario' => 'required|string|max:255',
                'asunto' => 'required|string|max:500',
                'mensaje' => 'required|string',
            ]);

            $pqrs = VentanillaPqrs::with(['radicado', 'tipoPqrs'])->find($id);

            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            // Enviar email usando Mailable
            Mail::to($validated['destinatario'])
                ->send(new PqrsNotificacionEmail($pqrs, $validated['mensaje'], $validated['asunto']));

            $this->auditVentanilla($pqrs, 'notified', $pqrs->radicado?->num_radicado ?? $pqrs->id, [
                'destinatario' => $validated['destinatario'],
                'asunto' => $validated['asunto'],
            ]);

            return $this->successResponse(null, 'Notificación enviada exitosamente a: '.$validated['destinatario']);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar la notificación', $e->getMessage(), 500);
        }
    }

    /**
     * Solicita un código OTP para firmar una PQRS electrónicamente.
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

            return $this->successResponse(null, 'Código OTP enviado al correo del usuario');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al solicitar el OTP', $e->getMessage(), 500);
        }
    }

    /**
     * Valida el código OTP para firmar una PQRS.
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
                return $this->errorResponse('Código OTP inválido o expirado', null, 403);
            }

            return $this->successResponse(['valido' => true], 'OTP validado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al validar el OTP', $e->getMessage(), 500);
        }
    }

    /**
     * Guarda la firma electrónica de una PQRS.
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

            return $this->successResponse(new PqrsResource($pqrs), 'PQRS firmada electrónicamente con éxito');
        } catch (\Exception $e) {
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
            return $this->errorResponse('Error al obtener PQRS pendientes de firma', $e->getMessage(), 500);
        }
    }
}
