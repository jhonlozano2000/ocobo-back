<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\CheckInRequest;
use App\Http\Requests\MiBandeja\StoreMiBandejaTempRequest;
use App\Http\Requests\MiBandeja\UpdateMiBandejaTempRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Rules\ArchivoLegible;
use App\Rules\ArchivoSeguro;
use App\Rules\MagicMime;
use App\Services\MiBandeja\GrupoColaborativoService;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador para gestionar grupos colaborativos temporales en Mi Bandeja.
 * Proporciona operaciones CRUD y envío a trámite de grupos.
 */
class MiBandejaTempController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    public function __construct(
        private readonly GrupoColaborativoService $grupoService,
    ) {
        $this->middleware('can:'.self::PERM.'Ver')->only(['index', 'show', 'buscarRadicados']);
        $this->middleware('can:'.self::PERM.'Crear')->only(['store']);
        $this->middleware('can:'.self::PERM.'Editar')->only(['update']);
        $this->middleware('can:'.self::PERM.'Eliminar')->only(['destroy']);
        $this->middleware('can:'.self::PERM.'Enviar Tramite')->only(['enviarTramite']);
    }

    /**
     * Lista grupos colaborativos temporales del usuario autenticado.
     *
     * @param  Request  $request  Solicitud HTTP con parámetros de búsqueda y filtro
     * @return JsonResponse Respuesta JSON con los grupos encontrados
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estadoGrupo = $request->get('estado_grupo', '');

            $query = MiBandejaTemp::with([
                'revisores.user.cargo',
                'firmantes.user.cargo',
                'proyectores.user.cargo',
                'aprobadores.user.cargo',
                'adjuntos',
                'plantilla',
                'notas:id,grupo_id,user_id,created_at',
                'lecturasNotas' => fn ($q) => $q->where('user_id', $userId),
            ])
            // Visibilidad: creador O cualquier miembro. Agrupado para que los
            // orWhere no rompan los filtros de búsqueda/estado.
                ->where(function ($q) use ($userId) {
                    $q->where('usua_crea_id', $userId)
                        ->orWhereHas('revisores', function ($sq) use ($userId) {
                            $sq->where('user_id', $userId);
                        })
                        ->orWhereHas('firmantes', function ($sq) use ($userId) {
                            $sq->where('user_id', $userId);
                        })
                        ->orWhereHas('proyectores', function ($sq) use ($userId) {
                            $sq->where('user_id', $userId);
                        })
                        ->orWhereHas('aprobadores', function ($sq) use ($userId) {
                            $sq->where('user_id', $userId);
                        });
                })
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($estadoGrupo) {
                $query->where('estado_grupo', $estadoGrupo);
            }

            $grupos = $query->limit(50)->get()->map(function ($grupo) use ($userId) {
                $totalNotas = $grupo->notas->count();
                $ultimaLectura = $grupo->lecturasNotas->first()?->ultima_lectura_at;
                $notasNoLeidas = $grupo->notas
                    ->where('user_id', '!=', $userId)
                    ->filter(function ($n) use ($ultimaLectura) {
                        return ! $ultimaLectura || $n->created_at->gt($ultimaLectura);
                    })
                    ->count();

                $grupo->total_notas = $totalNotas;
                $grupo->notas_no_leidas = $notasNoLeidas;

                return $grupo;
            });

            return $this->successResponse($grupos, 'Grupos colaborativos obtenidos');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener grupos', $e->getMessage(), 500);
        }
    }

    /**
     * Busca radicados (recibidos, internos, enviados) elegibles para vincular a un grupo colaborativo.
     *
     * @param  Request  $request  Solicitud HTTP con parámetros 'q', 'tipo' y 'limit'
     * @return JsonResponse Lista de radicados encontrados
     */
    public function buscarRadicados(Request $request): JsonResponse
    {
        try {
            $q = trim((string) $request->get('q', ''));
            $tipo = $request->get('tipo', 'todos');
            $limit = min((int) $request->get('limit', 15), 50);

            $resultados = collect();

            // 1. Recibidos
            if ($tipo === 'todos' || $tipo === 'recibido') {
                $queryReci = VentanillaRadicaReci::with('tercero')
                    ->select('id', 'num_radicado', 'asunto', 'tercero_id', 'created_at', 'num_folios')
                    ->orderBy('created_at', 'desc');

                if ($q !== '') {
                    $queryReci->where(function ($query) use ($q) {
                        $query->where('num_radicado', 'like', "%{$q}%")
                            ->orWhere('asunto', 'like', "%{$q}%")
                            ->orWhereHas('tercero', function ($qt) use ($q) {
                                $qt->where('nom_razo_soci', 'like', "%{$q}%")
                                    ->orWhere('num_docu_nit', 'like', "%{$q}%");
                            });
                    });
                }

                $recibidos = $queryReci->take($limit)->get()->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'radicado_tipo' => 'recibido',
                        'num_radicado' => $r->num_radicado,
                        'asunto' => $r->asunto,
                        'fecha' => $r->created_at?->format('Y-m-d'),
                        'tercero' => $r->tercero?->nom_razo_soci,
                        'tercero_nit' => $r->tercero?->num_docu_nit,
                        'num_folios' => $r->num_folios,
                    ];
                });
                $resultados = $resultados->merge($recibidos);
            }

            // 2. Internos
            if ($tipo === 'todos' || $tipo === 'interno') {
                $queryInt = VentanillaRadicaInterno::with('dependenciaOrigen')
                    ->select('id', 'num_radicado', 'asunto', 'dependencia_origen_id', 'created_at', 'num_folios')
                    ->orderBy('created_at', 'desc');

                if ($q !== '') {
                    $queryInt->where(function ($query) use ($q) {
                        $query->where('num_radicado', 'like', "%{$q}%")
                            ->orWhere('asunto', 'like', "%{$q}%");
                    });
                }

                $internos = $queryInt->take($limit)->get()->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'radicado_tipo' => 'interno',
                        'num_radicado' => $r->num_radicado,
                        'asunto' => $r->asunto,
                        'fecha' => $r->created_at?->format('Y-m-d'),
                        'dependencia' => $r->dependenciaOrigen ? $r->dependencia_origen['nombre'] : null,
                        'tercero' => null,
                        'tercero_nit' => null,
                        'num_folios' => $r->num_folios,
                    ];
                });
                $resultados = $resultados->merge($internos);
            }

            // 3. Enviados
            if ($tipo === 'todos' || $tipo === 'enviado') {
                $queryEnv = VentanillaRadicaEnviados::with('tercero')
                    ->select('id', 'num_radicado', 'asunto', 'tercero_id', 'created_at', 'num_folios')
                    ->orderBy('created_at', 'desc');

                if ($q !== '') {
                    $queryEnv->where(function ($query) use ($q) {
                        $query->where('num_radicado', 'like', "%{$q}%")
                            ->orWhere('asunto', 'like', "%{$q}%")
                            ->orWhereHas('tercero', function ($qt) use ($q) {
                                $qt->where('nom_razo_soci', 'like', "%{$q}%")
                                    ->orWhere('num_docu_nit', 'like', "%{$q}%");
                            });
                    });
                }

                $enviados = $queryEnv->take($limit)->get()->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'radicado_tipo' => 'enviado',
                        'num_radicado' => $r->num_radicado,
                        'asunto' => $r->asunto,
                        'fecha' => $r->created_at?->format('Y-m-d'),
                        'tercero' => $r->tercero?->nom_razo_soci,
                        'tercero_nit' => $r->tercero?->num_docu_nit,
                        'num_folios' => $r->num_folios,
                    ];
                });
                $resultados = $resultados->merge($enviados);
            }

            return $this->successResponse($resultados->values()->all(), 'Radicados encontrados');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Error al buscar radicados', $e->getMessage());
        }
    }

    /**
     * Crea un nuevo grupo colaborativo temporal.
     *
     * @param  StoreMiBandejaTempRequest  $request  Solicitud HTTP con datos validados del grupo
     * @return JsonResponse Respuesta JSON con el grupo creado
     */
    public function store(StoreMiBandejaTempRequest $request)
    {
        $validated = $request->validated();

        // Grupo de un documento de Mis Documentos: solo su dueño lo crea y el documento tiene un solo grupo activo
        if ($documento = Documento::find($validated['documento_id'] ?? null)) {
            Gate::authorize('gestionarUsuarios', $documento);
            if ($documento->grupoVigente()) {
                return $this->errorResponse('El documento ya tiene un grupo colaborativo activo', null, 422);
            }
        }

        try {
            $validated['usua_crea_id'] = Auth::id();
            $validated['estado'] = 'borrador';
            $validated['estado_grupo'] = 'activo';

            $grupo = MiBandejaTemp::create($validated);

            // Actualizar estado del radicado a EN_PROCESO si tiene fecha de vencimiento
            $radicado = $grupo->radicado;
            if ($radicado && $radicado->fec_venci) {
                $radicado->updateQuietly(['estado_trabajo' => RadicadoEstadoTrabajoService::ESTADO_EN_PROCESO]);
            }

            return $this->successResponse(
                $grupo->load(['revisores.user.cargo', 'firmantes.user.cargo', 'proyectores.user.cargo', 'aprobadores.user.cargo']),
                'Grupo colaborativo creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            Log::error('MiBandejaTempController@store error: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al crear grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Muestra el detalle de un grupo colaborativo temporal específico.
     *
     * @param  int  $id  Identificador del grupo
     * @return JsonResponse Respuesta JSON con el grupo encontrado
     */
    public function show($id)
    {
        $grupo = $this->autorizarGrupo($id);

        try {
            $grupo->load([
                'revisores.user.cargo',
                'firmantes.user.cargo',
                'proyectores.user.cargo',
                'aprobadores.user.cargo',
                'adjuntos',
                'creadoPor.cargo',
                'plantilla',
                'versiones.subidoPor',
                'firmaEventos.user',
            ]);

            if ($grupo->radicado_id && $grupo->radicado_tipo) {
                if ($grupo->radicado_tipo === 'recibido') {
                    $grupo->load(['radicadoRecibido.tercero', 'radicadoRecibido.medioRecepcion']);
                } elseif ($grupo->radicado_tipo === 'interno') {
                    $grupo->load(['radicadoInterno.dependenciaOrigen', 'radicadoInterno.destinatarios']);
                } elseif ($grupo->radicado_tipo === 'enviado') {
                    $grupo->load('radicadoEnviado.destinatarios');
                }
            }

            $grupo->append('radicado');

            return $this->successResponse($grupo, 'Detalle del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener grupo', $e->getMessage());
        }
    }

    /**
     * Actualiza un grupo colaborativo temporal existente.
     *
     * @param  UpdateMiBandejaTempRequest  $request  Solicitud HTTP con datos actualizados
     * @param  int  $id  Identificador del grupo a actualizar
     * @return JsonResponse Respuesta JSON con el grupo actualizado
     */
    public function update(UpdateMiBandejaTempRequest $request, $id)
    {
        $grupo = $this->autorizarGrupo($id);

        try {
            $datos = $request->validated();

            // Si se solicita cambiar la plantilla asignada o el radicado vinculado
            $cambioPlantilla = array_key_exists('plantilla_id', $datos) && (int) ($datos['plantilla_id'] ?? 0) !== (int) ($grupo->plantilla_id ?? 0);
            $cambioRadicado = (array_key_exists('radicado_id', $datos) && (int) ($datos['radicado_id'] ?? 0) !== (int) ($grupo->radicado_id ?? 0))
                || (array_key_exists('radicado_tipo', $datos) && ($datos['radicado_tipo'] ?? '') !== ($grupo->radicado_tipo ?? ''));

            if ($cambioPlantilla || $cambioRadicado) {
                // Si algún firmante ya estampó firma electrónica, se prohíbe cambiar la plantilla o radicado
                if ($grupo->firmantes()->whereNotNull('fechor_firmado')->exists()) {
                    return $this->warningResponse('No se puede cambiar la plantilla o radicado porque el documento ya tiene firmas electrónicas suscritas.');
                }

                // Si ya existían versiones previas, eliminarlas para regenerar con la nueva plantilla/radicado
                $versiones = $grupo->versiones;
                if ($versiones->isNotEmpty()) {
                    foreach ($versiones as $ver) {
                        if ($ver->ruta_completa && Storage::disk('plantillas_grupos')->exists($ver->ruta_completa)) {
                            Storage::disk('plantillas_grupos')->delete($ver->ruta_completa);
                        }
                        $ver->delete();
                    }
                    $datos['plantilla_cargada'] = false;
                }

                // Restablecer el estado de descarga de plantilla para los miembros con tarea pendiente
                $grupo->revisores()->where('estado_tarea', '!=', 'cumplido')->update(['descargo_plantilla' => false, 'subio_plantilla' => false]);
                $grupo->proyectores()->where('estado_tarea', '!=', 'cumplido')->update(['descargo_plantilla' => false, 'subio_plantilla' => false]);
                $grupo->aprobadores()->where('estado_tarea', '!=', 'cumplido')->update(['descargo_plantilla' => false, 'subio_plantilla' => false]);
                $grupo->firmantes()->where('estado_tarea', '!=', 'cumplido')->update(['descargo_plantilla' => false, 'subio_plantilla' => false]);
            }

            $grupo->update($datos);

            return $this->successResponse(
                $grupo->load(['revisores.user.cargo', 'firmantes.user.cargo', 'proyectores.user.cargo', 'aprobadores.user.cargo', 'adjuntos', 'plantilla']),
                'Grupo actualizado exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al actualizar grupo', $e->getMessage());
        }
    }

    /**
     * Elimina un grupo colaborativo temporal (solo si no está activo).
     *
     * @param  int  $id  Identificador del grupo a eliminar
     * @return JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($id)
    {
        $grupo = $this->autorizarGrupo($id, soloCreador: true);

        try {
            if ($grupo->estado_grupo === 'activo') {
                return $this->errorResponse('No se puede eliminar un grupo activo. Anúlelo primero.', null, 422);
            }

            $grupo->delete();

            return $this->successResponse(null, 'Grupo eliminado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga el documento del grupo y adquiere el bloqueo de edición (check-out).
     *
     * @param  int  $id  Identificador del grupo
     * @return BinaryFileResponse|JsonResponse
     */
    public function checkOut($id)
    {
        $grupo = $this->autorizarGrupo($id);

        try {
            $resultado = $this->grupoService->checkOut($grupo, Auth::user());

            return response()->download(
                $resultado['archivo'],
                $resultado['nombre'],
                ['Content-Type' => $resultado['mime']]
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al descargar documento', $e->getMessage(), 500);
        }
    }

    /**
     * Sube una nueva versión del documento colaborativo y libera el bloqueo de edición (check-in).
     *
     * @param  CheckInRequest  $request  Solicitud validada con el archivo y comentario
     * @param  int  $id  Identificador del grupo
     * @return JsonResponse Respuesta JSON con la versión creada
     */
    public function checkIn(CheckInRequest $request, $id)
    {
        $grupo = $this->autorizarGrupo($id);

        try {
            $version = $this->grupoService->checkIn(
                $grupo,
                Auth::user(),
                $request->file('archivo'),
                $request->input('comentario')
            );

            $version->load('subidoPor');

            return $this->successResponse($version, 'Documento actualizado exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al subir documento', $e->getMessage(), 500);
        }
    }

    /**
     * Sube la versión inicial (1.0) del documento para un grupo que no parte de plantilla.
     *
     * @param  Request  $request  Solicitud con el archivo inicial
     * @param  int  $id  Identificador del grupo
     * @return JsonResponse Respuesta JSON con la versión creada
     */
    public function subirVersionInicial(Request $request, $id)
    {
        try {
            $request->validate([
                'archivo' => ['bail', 'required', 'file', new ArchivoLegible, 'max:51200', new ArchivoSeguro(CheckInRequest::EXTENSIONES), 'mimes:'.implode(',', CheckInRequest::EXTENSIONES), new MagicMime],
            ]);

            $grupo = $this->autorizarGrupo($id);

            if ($grupo->versiones()->count() > 0) {
                return $this->errorResponse('El grupo ya tiene un documento. Use check-in para actualizar.', null, 422);
            }

            $version = $this->grupoService->subirVersionInicial(
                $grupo,
                Auth::user(),
                $request->file('archivo')
            );

            $grupo->update([
                'plantilla_cargada' => true,
                'estado' => 'en_curso',
            ]);

            $version->load('subidoPor');

            return $this->successResponse($version, 'Documento subido exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al subir documento', $e->getMessage(), 500);
        }
    }

    /**
     * Envía un grupo colaborativo temporal a trámite.
     * Verifica que todos los miembros hayan terminado su trabajo y que quien envía sea firmante.
     *
     * @param  Request  $request  Solicitud con posible respuesta final
     * @param  int  $id  Identificador del grupo a enviar a trámite
     * @return JsonResponse Respuesta JSON con el grupo actualizado
     */
    public function enviarTramite(Request $request, $id)
    {
        $grupo = $this->autorizarGrupo($id);

        try {
            $grupo->load([
                'revisores', 'firmantes', 'proyectores', 'aprobadores', 'adjuntos',
            ]);

            $userId = Auth::id();
            $esFirmante = $grupo->firmantes->contains('user_id', $userId);
            if (! $esFirmante) {
                return $this->errorResponse('Solo los funcionarios firmantes pueden realizar el envío de la respuesta.', null, 403);
            }

            $grupoActualizado = $this->grupoService->enviarTramite(
                $grupo,
                $request->input('respuesta_final')
            );

            return $this->successResponse($grupoActualizado, 'Grupo enviado a trámite exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al enviar a trámite', $e->getMessage(), 500);
        }
    }

    /**
     * Anula un grupo colaborativo. Solo el creador puede realizar esta acción.
     *
     * @param  int  $id  Identificador del grupo
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function anular($id)
    {
        $grupo = $this->autorizarGrupo($id, soloCreador: true);

        try {
            $this->grupoService->anular($grupo, Auth::user());

            return $this->successResponse(null, 'Grupo anulado exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 403);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al anular grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Marca la tarea del usuario autenticado como terminada en el grupo colaborativo.
     *
     * @param  Request  $request  Solicitud HTTP
     * @param  int  $id  Identificador del grupo
     * @return JsonResponse Respuesta JSON con el estado del grupo y tarea cumplida
     */
    public function marcarTerminado(Request $request, $id)
    {
        $grupo = $this->autorizarGrupo($id);

        try {
            $resultado = $this->grupoService->marcarCumplido(
                $grupo,
                Auth::user(),
                $request->only(['metodo', 'otp', 'password'])
            );

            $grupo->load(['revisores', 'firmantes', 'proyectores', 'aprobadores']);

            $responseData = [
                'grupo' => $grupo,
                'rol' => $resultado['rol'],
                'todos_cumplidos' => $resultado['todos_cumplidos'],
                'nuevo_estado' => $resultado['nuevo_estado'],
            ];

            return $this->successResponse($responseData, 'Tarea marcada como terminada');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}
