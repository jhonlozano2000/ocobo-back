<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Helpers\ArchivoHelper;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\Enviados\StoreRadicadoEnviadoRequest;
use App\Http\Requests\Ventanilla\Enviados\ListRadicadosEnviadosRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosRespona;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\AuditViewTrait;
use App\Traits\VentanillaAuditTrait;

class VentanillaRadicaEnviadosController extends Controller
{
    use ApiResponseTrait, AuditViewTrait, VentanillaAuditTrait;

    private const PERM = 'Radicar -> Cores. Enviada -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Listar')->only(['index', 'listarRadicados', 'estadisticas']);
        $this->middleware('can:' . self::PERM . 'Crear')->only(['store']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['show', 'lineaTiempo']);
        $this->middleware('can:' . self::PERM . 'Editar')->only(['update']);
        $this->middleware('can:' . self::PERM . 'Actualizar asunto')->only(['updateAsunto']);
        $this->middleware('can:' . self::PERM . 'Atualizar fechas de radicados')->only(['updateFechas']);
        $this->middleware('can:' . self::PERM . 'Actualizar clasificacion de radicados')->only(['updateClasificacionDocumental']);
        $this->middleware('can:' . self::PERM . 'Eliminar')->only(['destroy']);
        $this->middleware('can:' . self::PERM . 'Notificar Email')->only(['enviarNotificacion']);
    }

    public function index(ListRadicadosEnviadosRequest $request)
    {
        try {
            $query = VentanillaRadicaEnviados::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('num_radicado', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $query->whereBetween('created_at', [$request->fecha_desde, $request->fecha_hasta]);
            }

            if ($request->filled('clasifica_documen_id')) {
                $query->where('clasifica_documen_id', $request->clasifica_documen_id);
            }

            if ($request->filled('tercero_enviado_id')) {
                $query->where('tercero_id', $request->tercero_enviado_id);
            }

            if ($request->filled('medio_enviado_id')) {
                $query->where('medio_enviado_id', $request->medio_enviado_id);
            }

            if ($request->filled('usuario_responsable')) {
                $query->whereHas('responsables', function ($q) use ($request) {
                    $q->whereHas('userCargo', function ($qc) use ($request) {
                        $qc->where('user_id', $request->usuario_responsable);
                    });
                });
            }

            $query->with([
                'clasificacionDocumental',
                'tercero',
                'medioEnvio',
                'tipoRespuesta',
                'usuarioCreaRadicado',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
            ])->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $radicados = $query->paginate($perPage);

            $radicados->getCollection()->transform(function ($radicado) {
                $clasifInfo = $radicado->getClasificacionDocumentalInfo();
                $responsablesInfo = $radicado->getResponsablesInfo();
                $terceroInfo = $radicado->tercero;

                $radicado->unsetRelation('clasificacionDocumental');
                $radicado->setAttribute('clasificacion_documental', $clasifInfo);
                $radicado->setAttribute('tercero_enviado', $terceroInfo);
                $radicado->setAttribute('tercero_enviado_nombre', $terceroInfo?->nom_razo_soci);
                $radicado->responsables = $responsablesInfo['responsables'];
                $radicado->total_responsables = $responsablesInfo['total_responsables'];
                $radicado->total_custodios = $responsablesInfo['total_custodios'];
                $radicado->tiene_archivo_digital = !empty($radicado->archivo_digital);
                $radicado->estado_trabajo_info = $radicado->getEstadoTrabajoInfo();
                $radicado->dias_para_vencer = $radicado->getDiasParaVencerAttribute();
                $radicado->is_vencida = $radicado->isVencida();
                return $radicado;
            });

            return $this->successResponse($radicados, 'Listado de radicados enviados obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de radicados enviados', $e->getMessage(), 500);
        }
    }

    public function store(StoreRadicadoEnviadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $numRadicado = $this->generarNumeroRadicado();

            $radicado = new VentanillaRadicaEnviados($validatedData);
            $radicado->num_radicado = $numRadicado;
            $radicado->save();

            DB::commit();

            Cache::forget('ventanilla_enviados_estadisticas');

            $this->auditVentanilla($radicado, 'created', $radicado->num_radicado);

            return $this->successResponse(
                $radicado->load(['clasificacionDocumental', 'tercero', 'medioEnvio', 'tipoRespuesta']),
                'Radicado enviado creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el radicado enviado', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::with([
                'clasificacionDocumental',
                'tercero',
                'medioEnvio',
                'tipoRespuesta',
                'servidorArchivos',
                'usuarioCreaRadicado',
                'usuarioSubio',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
                'respuestas.userCargo',
                'firmas.userCargo.user',
                'firmas.userCargo.cargo',
                'proyectores.userCargo.user',
                'proyectores.userCargo.cargo',
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            // Actualizar estado de trabajo automáticamente
            $radicado->actualizarEstadoTrabajo();
            $radicado->refresh();

            // Registrar acceso al radicado (ISO 27001 - Trazabilidad)
            $this->auditView($radicado, "Consulta detallada del radicado enviado: {$radicado->num_radicado}");

            $radicado->loadClasificacionConJerarquia();

            $documentos = [
                'archivo_principal' => $radicado->archivo_digital ? [
                    'nombre' => basename($radicado->archivo_digital),
                    'ruta' => $radicado->archivo_digital,
                ] : null,
                'total_archivos' => $radicado->archivo_digital ? 1 : 0,
                'tiene_archivo_principal' => !empty($radicado->archivo_digital),
            ];

            $responsablesInfo = $radicado->getResponsablesInfo();
            
            $firmantesInfo = [];
            foreach ($radicado->firmas as $f) {
                $user = $f->userCargo?->user;
                $cargo = $f->userCargo?->cargo;
                if ($user) {
                    $firmantesInfo[] = [
                        'id' => $f->id,
                        'usuario' => ['id' => $user->id, 'nombres' => $user->nombres, 'apellidos' => $user->apellidos],
                        'cargo' => $cargo ? ['id' => $cargo->id, 'nombre' => $cargo->nom_organico] : null,
                    ];
                }
            }
            
            $proyectoresInfo = [];
            foreach ($radicado->proyectores as $p) {
                $user = $p->userCargo?->user;
                $cargo = $p->userCargo?->cargo;
                if ($user) {
                    $proyectoresInfo[] = [
                        'id' => $p->id,
                        'usuario' => ['id' => $user->id, 'nombres' => $user->nombres, 'apellidos' => $user->apellidos],
                        'cargo' => $cargo ? ['id' => $cargo->id, 'nombre' => $cargo->nom_organico] : null,
                    ];
                }
            }
            
            $terceroInfo = $radicado->tercero;

            $data = $radicado->toArray();
            
            // Unset relations to prevent overwriting custom attributes
            $radicado->unsetRelation('clasificacionDocumental');
            $radicado->unsetRelation('tercero');
            $radicado->unsetRelation('responsables');
            $radicado->unsetRelation('firmas');
            $radicado->unsetRelation('proyectores');
            
            $data = $radicado->toArray();
            $data['clasificacion_documental'] = $radicado->getClasificacionDocumentalInfo();
            $data['tercero_enviado'] = $terceroInfo;
            $data['tercero_enviado_nombre'] = $terceroInfo?->nom_razo_soci;
            $data['documentos'] = $documentos;
            $data['usuario_creo_radicado'] = $radicado->getInfoUsuarioCrea();
            $data['usuario_subio'] = $radicado->getInfoUsuarioSubio();
            $data['responsables'] = $responsablesInfo['responsables'];
            $data['total_responsables'] = $responsablesInfo['total_responsables'];
            $data['total_custodios'] = $responsablesInfo['total_custodios'];
            $data['firmantes'] = $firmantesInfo;
            $data['proyectores'] = $proyectoresInfo;
            $data['estado_trabajo_info'] = $radicado->getEstadoTrabajoInfo();
            $data['dias_para_vencer'] = $radicado->getDiasParaVencerAttribute();
            $data['is_vencida'] = $radicado->isVencida();
            $data['tiene_archivo_digital'] = $radicado->tieneArchivoDigital();
            $data['info_archivo_digital'] = $radicado->getInfoArchivoDigital();

            return $this->successResponse($data, 'Radicado enviado encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el radicado enviado', $e->getMessage(), 500);
        }
    }

    public function update($id, StoreRadicadoEnviadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $radicado->update($request->validated());

            $this->auditVentanilla($radicado, 'updated', $radicado->num_radicado, [
                'campos' => array_keys($request->validated())
            ]);

            DB::commit();

            return $this->successResponse(
                $radicado->load(['clasificacionDocumental', 'tercero', 'medioEnvio', 'tipoRespuesta']),
                'Radicado enviado actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el radicado enviado', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            if ($radicado->archivo_digital) {
                ArchivoHelper::eliminarArchivo($radicado->archivo_digital, 'radicados_enviados');
            }

            $radicado->responsables()->delete();
            $radicado->respuestas()->delete();
            $radicado->firmas()->delete();
            $radicado->proyectores()->delete();
            $numRadicado = $radicado->num_radicado;
            $radicado->delete();

            $this->auditVentanilla($radicado, 'deleted', $numRadicado);

            DB::commit();

            Cache::forget('ventanilla_enviados_estadisticas');

            return $this->successResponse(null, 'Radicado enviado eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el radicado enviado', $e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:ventanilla_radica_enviados,id'
            ], [
                'ids.required' => 'Se debe enviar un array de IDs no vacío',
                'ids.array' => 'Los IDs deben ser un array',
                'ids.*.exists' => 'Uno o más IDs no existen'
            ]);

            $ids = $request->ids;
            $eliminados = 0;
            $fallidos = 0;
            $errores = [];

            DB::beginTransaction();

            foreach ($ids as $id) {
                try {
                    $radicado = VentanillaRadicaEnviados::find($id);

                    if (!$radicado) {
                        $fallidos++;
                        continue;
                    }

                    if ($radicado->archivo_digital) {
                        ArchivoHelper::eliminarArchivo($radicado->archivo_digital, 'radicados_enviados');
                    }

                    $archivosAdicionales = $radicado->archivos()->get();
                    foreach ($archivosAdicionales as $archivo) {
                        if (!empty($archivo->archivo)) {
                            ArchivoHelper::eliminarArchivo($archivo->archivo, 'radicados_enviados');
                        }
                    }

                    $radicado->archivos()->delete();
                    $radicado->responsables()->delete();
                    $radicado->respuestas()->delete();
                    $radicado->firmas()->delete();
                    $radicado->proyectores()->delete();
                    $radicado->delete();
                    $eliminados++;
                } catch (\Exception $e) {
                    $fallidos++;
                    $errores[] = ['id' => $id, 'error' => $e->getMessage()];
                }
            }

            DB::commit();

            Cache::forget('ventanilla_enviados_estadisticas');

            $mensaje = "{$eliminados} radicacion(es) eliminada(s) exitosamente";
            if ($fallidos > 0) {
                $mensaje .= ", {$fallidos} fallida(s)";
            }

            return $this->successResponse([
                'eliminados' => $eliminados,
                'fallidos' => $fallidos,
                'errores' => $errores
            ], $mensaje);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar radicaciones', $e->getMessage(), 500);
        }
    }

    public function enviarNotificacionTercero($id): JsonResponse
    {
        try {
            $radicado = VentanillaRadicaEnviados::with([
                'terceroEnviado',
                'archivos',
            ])->findOrFail($id);

            $enviado = \App\Helpers\AcuseReciboHelper::enviarNotificacionConAdjuntos($radicado, true);

            if ($enviado) {
                return $this->successResponse([
                    'radicado_id' => $radicado->id,
                    'email_enviado' => $radicado->terceroEnviado?->email,
                ], 'Notificación enviada al tercero');
            }

            return $this->errorResponse(
                'No se pudo enviar la notificación. Verifique que el tercero tenga email y acepte notificaciones.',
                null,
                422
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Radicado no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar la notificación', $e->getMessage(), 500);
        }
    }

    public function estadisticas()
    {
        try {
            $cacheKey = 'ventanilla_enviados_estadisticas';
            $cached = Cache::get($cacheKey);

            if ($cached) {
                return $this->successResponse($cached, 'Estadísticas obtenidas exitosamente');
            }

            $totalRadicados = VentanillaRadicaEnviados::count();
            $faltanArchivoDigital = VentanillaRadicaEnviados::where(function ($q) {
                $q->whereNull('archivo_digital')->orWhere('archivo_digital', '');
            })->count();
            $faltanImprimirRotulo = VentanillaRadicaEnviados::where('impri_rotulo', '!=', 1)->count();

            $radicadosHoy = VentanillaRadicaEnviados::whereDate('created_at', Carbon::now()->format('Y-m-d'))->count();
            $radicadosEstaSemana = VentanillaRadicaEnviados::whereBetween('created_at', [
                Carbon::now()->startOfWeek()->format('Y-m-d'),
                Carbon::now()->endOfWeek()->format('Y-m-d'),
            ])->count();
            $radicadosEsteMes = VentanillaRadicaEnviados::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();

            $totalConArchivos = $totalRadicados - $faltanArchivoDigital;
            $totalPendientes = VentanillaRadicaEnviados::where(function ($q) {
                $q->whereNull('archivo_digital')->orWhere('archivo_digital', '');
            })->whereDoesntHave('responsables')->count();
            $totalEnProceso = VentanillaRadicaEnviados::whereNotNull('archivo_digital')
                ->where('archivo_digital', '!=', '')
                ->whereHas('responsables')
                ->count();

            $estadisticas = [
                'total_radicados' => $totalRadicados,
                'total_pendientes' => $totalPendientes,
                'total_proceso' => $totalEnProceso,
                'total_con_archivos' => $totalConArchivos,
                'faltan_archivo_digital' => $faltanArchivoDigital,
                'faltan_imprimir_rotulo' => $faltanImprimirRotulo,
                'radicados_hoy' => $radicadosHoy,
                'radicados_esta_semana' => $radicadosEstaSemana,
                'radicados_este_mes' => $radicadosEsteMes,
                'porcentaje_con_archivo' => $totalRadicados > 0 ? round((($totalRadicados - $faltanArchivoDigital) / $totalRadicados) * 100, 2) : 0,
                'porcentaje_rotulos_impresos' => $totalRadicados > 0 ? round((($totalRadicados - $faltanImprimirRotulo) / $totalRadicados) * 100, 2) : 0,
            ];

            Cache::put($cacheKey, $estadisticas, now()->addMinutes(10));

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }

    public function listarRadicados(\App\Http\Requests\Ventanilla\Enviados\ListRadicadosEnviadosRequest $request)
    {
        try {
            $query = VentanillaRadicaEnviados::with([
                'clasificacionDocumental',
                'tercero',
                'medioEnvio',
                'tipoRespuesta',
            ]);

            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $query->whereBetween('created_at', [$request->fecha_desde, $request->fecha_hasta]);
            }

            if ($request->filled('usuario_responsable')) {
                $query->whereHas('responsables', function ($q) use ($request) {
                    $q->whereHas('userCargo', fn ($qc) => $qc->where('user_id', $request->usuario_responsable));
                });
            }

            $perPage = $request->get('per_page', 10);
            $radicados = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($radicados, 'Radicados enviados obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los radicados enviados', $e->getMessage(), 500);
        }
    }

    public function updateAsunto($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate(['asunto' => 'required|string|max:300']);

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $responsableVisto = VentanillaRadicaEnviadosRespona::where('radica_enviado_id', $id)
                ->whereNotNull('fechor_visto')
                ->exists();

            if ($responsableVisto) {
                return $this->errorResponse(
                    'No se puede editar el asunto porque al menos un responsable ya ha visto el documento',
                    null,
                    400
                );
            }

            $radicado->update(['asunto' => $request->asunto]);
            DB::commit();

            return $this->successResponse([
                'id' => $radicado->id,
                'asunto' => $radicado->asunto,
                'updated_at' => $radicado->updated_at,
            ], 'Asunto actualizado exitosamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el asunto', $e->getMessage(), 500);
        }
    }

    public function updateFechas($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $responsableVisto = VentanillaRadicaEnviadosRespona::where('radica_enviado_id', $id)
                ->whereNotNull('fechor_visto')
                ->exists();

            if ($responsableVisto) {
                return $this->errorResponse(
                    'No se pueden actualizar las fechas porque al menos un responsable ya ha visto el documento',
                    null,
                    422
                );
            }

            $request->validate([
                'fec_docu' => 'nullable|date',
            ], [
                'fec_docu.date' => 'La fecha del documento debe ser una fecha válida',
            ]);

            if (!$request->filled('fec_docu')) {
                return $this->errorResponse('No se proporcionó fecha para actualizar', null, 422);
            }

            $radicado->update(['fec_docu' => $request->fec_docu]);
            DB::commit();

            return $this->successResponse([
                'id' => $radicado->id,
                'fec_docu' => $radicado->fec_docu,
                'updated_at' => $radicado->updated_at,
            ], 'Fecha actualizada exitosamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la fecha', $e->getMessage(), 500);
        }
    }

    public function updateClasificacionDocumental($id, Request $request)
    {
        try {
            $request->validate([
                'clasifica_documen_id' => 'required|integer|exists:clasificacion_documental_trd,id',
            ], [
                'clasifica_documen_id.required' => 'La clasificación documental es obligatoria.',
                'clasifica_documen_id.exists' => 'La clasificación documental no es válida.',
            ]);

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $responsableHaVisto = VentanillaRadicaEnviadosRespona::where('radica_enviado_id', $id)
                ->whereNotNull('fechor_visto')
                ->exists();

            if ($responsableHaVisto) {
                return $this->errorResponse(
                    'No se puede editar la clasificación documental porque al menos un responsable ya ha visto el documento',
                    null,
                    422
                );
            }

            $radicado->update(['clasifica_documen_id' => $request->clasifica_documen_id]);

            return $this->successResponse(
                $radicado->fresh(['clasificacionDocumental']),
                'Clasificación documental actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la clasificación documental', $e->getMessage(), 500);
        }
    }

    public function lineaTiempo($id): JsonResponse
    {
        try {
            $radicado = VentanillaRadicaEnviados::with([
                'usuarioCreaRadicado',
                'usuarioSubio',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
                'firmas.userCargo.user',
                'firmas.userCargo.cargo',
                'proyectores.userCargo.user',
                'proyectores.userCargo.cargo',
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $eventos = [];

            $eventos[] = [
                'fecha' => is_object($radicado->created_at) ? $radicado->created_at->toIso8601String() : $radicado->created_at,
                'tipo' => 'radicado_creado',
                'titulo' => 'Radicado creado',
                'descripcion' => 'Se creó el radicado ' . $radicado->num_radicado,
                'usuario' => $radicado->usuarioCreaRadicado?->getInfoUsuario(),
                'datos' => ['num_radicado' => $radicado->num_radicado, 'radicado_id' => $radicado->id],
            ];

            if ($radicado->updated_at->gt($radicado->created_at)) {
                $eventos[] = [
                    'fecha' => is_object($radicado->updated_at) ? $radicado->updated_at->toIso8601String() : $radicado->updated_at,
                    'tipo' => 'radicado_actualizado',
                    'titulo' => 'Radicado actualizado',
                    'descripcion' => 'Se actualizaron datos del radicado',
                    'usuario' => null,
                    'datos' => [
                        'asunto' => $radicado->asunto,
                        'fec_docu' => $radicado->fec_docu ? (is_string($radicado->fec_docu) ? $radicado->fec_docu : $radicado->fec_docu?->toDateString()) : null,
                        'clasifica_documen_id' => $radicado->clasifica_documen_id,
                    ],
                ];
            }

            if (!empty($radicado->archivo_digital)) {
                $eventos[] = [
                    'fecha' => is_object($radicado->updated_at) ? $radicado->updated_at->toIso8601String() : $radicado->updated_at,
                    'tipo' => 'archivo_digital_subido',
                    'titulo' => 'Archivo digital subido',
                    'descripcion' => 'Se cargó el archivo digital: ' . basename($radicado->archivo_digital),
                    'usuario' => $radicado->usuarioSubio?->getInfoUsuario(),
                    'datos' => [
                        'archivo_nombre' => basename($radicado->archivo_digital),
                        'extension' => pathinfo($radicado->archivo_digital, PATHINFO_EXTENSION),
                        'ruta' => $radicado->archivo_digital,
                    ],
                ];
            }

            foreach ($radicado->responsables as $responsable) {
                $user = $responsable->userCargo?->user?->getInfoUsuario();
                $cargo = $responsable->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => is_object($responsable->created_at) ? $responsable->created_at->toIso8601String() : $responsable->created_at,
                    'tipo' => 'responsable_asignado',
                    'titulo' => 'Responsable asignado',
                    'descripcion' => $cargo
                        ? 'Se asignó como responsable' . ($responsable->custodio ? ' (custodio)' : '') . ': ' . $cargo->nom_organico
                        : 'Se asignó un responsable',
                    'usuario' => $user,
                    'custodio' => $responsable->custodio,
                    'datos' => [
                        'responsable_id' => $responsable->id,
                        'cargo' => $cargo ? ['id' => $cargo->id, 'nombre' => $cargo->nom_organico, 'codigo' => $cargo->cod_organico] : null,
                    ],
                ];
            }

            foreach ($radicado->firmas as $firma) {
                $user = $firma->userCargo?->user?->getInfoUsuario();
                $cargo = $firma->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => $firma->created_at,
                    'tipo' => 'firma_asignada',
                    'titulo' => 'Responsable de firma',
                    'descripcion' => $cargo ? 'Asignado: ' . $cargo->nom_organico : 'Se asignó responsable de firma',
                    'usuario' => $user,
                    'datos' => ['firma_id' => $firma->id],
                ];
            }

            foreach ($radicado->proyectores as $proyector) {
                $user = $proyector->userCargo?->user?->getInfoUsuario();
                $cargo = $proyector->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => $proyector->created_at,
                    'tipo' => 'proyector_asignado',
                    'titulo' => 'Proyector asignado',
                    'descripcion' => $cargo ? 'Asignado: ' . $cargo->nom_organico : 'Se asignó proyector',
                    'usuario' => $user,
                    'datos' => ['proyector_id' => $proyector->id],
                ];
            }

            usort($eventos, fn ($a, $b) => 
                is_object($a['fecha']) ? $a['fecha']->getTimestamp() : (is_numeric($a['fecha']) ? $a['fecha'] : strtotime($a['fecha'] ?? 'now'))
                - 
                (is_object($b['fecha']) ? $b['fecha']->getTimestamp() : (is_numeric($b['fecha']) ? $b['fecha'] : strtotime($b['fecha'] ?? 'now')))
            );

            $lineaTiempo = array_map(function ($e) {
                $e['fecha'] = is_object($e['fecha']) ? $e['fecha']->toIso8601String() : $e['fecha'];
                return $e;
            }, $eventos);

            $resumenPorTipo = [];
            foreach ($eventos as $e) {
                $t = $e['tipo'];
                $resumenPorTipo[$t] = ($resumenPorTipo[$t] ?? 0) + 1;
            }

            return $this->successResponse([
                'radicado' => [
                    'id' => $radicado->id,
                    'num_radicado' => $radicado->num_radicado,
                    'asunto' => $radicado->asunto,
                    'created_at' => is_object($radicado->created_at) ? $radicado->created_at->toIso8601String() : $radicado->created_at,
                    'updated_at' => is_object($radicado->updated_at) ? $radicado->updated_at->toIso8601String() : $radicado->updated_at,
                    'fec_docu' => $radicado->fec_docu ? (is_string($radicado->fec_docu) ? $radicado->fec_docu : (is_object($radicado->fec_docu) ? $radicado->fec_docu?->toDateString() : null)) : null,
                    'tiene_archivo_digital' => !empty($radicado->archivo_digital),
                    'total_responsables' => $radicado->responsables->count(),
                ],
                'resumen' => [
                    'total_eventos' => count($lineaTiempo),
                    'por_tipo' => $resumenPorTipo,
                ],
                'eventos' => $lineaTiempo,
            ], 'Línea de tiempo obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la línea de tiempo', $e->getMessage(), 500);
        }
    }

    public function enviarNotificacion($id): JsonResponse
    {
        try {
            $radicado = VentanillaRadicaEnviados::with([
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
                'clasificacionDocumental',
                'tercero',
                'medioEnvio',
                'usuarioCreaRadicado',
                'usuarioSubio',
                'archivos',
            ])->select('ventanilla_radica_enviados.*')->findOrFail($id);

            $resultado = app(\App\Services\Notificaciones\NotificacionCorrespondenciaService::class)
                ->enviarRadicadoEnviado($radicado);

            return $this->successResponse([
                'radicado_id' => $radicado->id,
                ...$resultado,
            ], 'Notificaciones enviadas exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Radicado enviado no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar las notificaciones', $e->getMessage(), 500);
        }
    }

    private function generarNumeroRadicado(?string $cod_dependencia = null): string
    {
        $formato = ConfigVarias::getValor('formato_num_radicado_envi', 'YYYYMMDD-#####');

        preg_match('/#+/', $formato, $matches);
        $longitudConsecutivo = isset($matches[0]) ? strlen($matches[0]) : 5;

        $fecha = Carbon::now();
        $yyyy = $fecha->format('Y');
        $mm = $fecha->format('m');
        $dd = $fecha->format('d');

        $ultimoRadicado = VentanillaRadicaEnviados::whereYear('created_at', $yyyy)
            ->orderBy('id', 'desc')
            ->value('num_radicado');

        preg_match('/\d+$/', $ultimoRadicado ?? '', $consecutivoAnterior);
        $nuevoConsecutivo = isset($consecutivoAnterior[0]) ? (int) $consecutivoAnterior[0] + 1 : 1;

        $consecutivo = str_pad((string) $nuevoConsecutivo, $longitudConsecutivo, '0', STR_PAD_LEFT);

        $variables = [
            'YYYY' => $yyyy,
            'MM' => $mm,
            'DD' => $dd,
            'COD_DEPEN' => $cod_dependencia,
            str_repeat('#', $longitudConsecutivo) => $consecutivo,
        ];

        foreach ($variables as $key => $value) {
            if ($value !== null && str_contains($formato, $key)) {
                $formato = str_replace($key, $value, $formato);
            }
        }

        return $formato;
    }

    public function searchByOcr(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->get('q', '');
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 20);

            if (strlen($searchTerm) < 3) {
                return $this->errorResponse(
                    'El término de búsqueda debe tener al menos 3 caracteres',
                    null,
                    422
                );
            }

            $perPage = min(max($perPage, 1), 100);

            $radicados = VentanillaRadicaEnviados::with([
                    'terceroEnviado',
                    'clasificacionDocumental',
                    'responsables.userCargo.user'
                ])
                ->where(function ($q) use ($searchTerm) {
                    $q->orWhere('num_radicado', 'LIKE', "%{$searchTerm}%");
                    $q->orWhere('asunto', 'LIKE', "%{$searchTerm}%");
                    $q->orWhere(function ($subQ) use ($searchTerm) {
                        $subQ->whereNotNull('ocr')
                             ->where('ocr', '!=', '')
                             ->where('ocr', 'LIKE', "%{$searchTerm}%");
                    });
                    $q->orWhereHas('terceroEnviado', function ($subQ) use ($searchTerm) {
                        $subQ->where('nom_razo_soci', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('num_docu_nit', 'LIKE', "%{$searchTerm}%");
                    });
                    $q->orWhereHas('responsables', function ($subQ) use ($searchTerm) {
                        $subQ->whereHas('userCargo.user', function ($userQ) use ($searchTerm) {
                            $userQ->where('nombres', 'LIKE', "%{$searchTerm}%")
                                  ->orWhere('apellidos', 'LIKE', "%{$searchTerm}%");
                        });
                    });
                })
                ->select([
                    'id',
                    'num_radicado',
                    'asunto',
                    'fec_docu',
                    'fec_venci',
                    'tercero_id',
                    'clasifica_documen_id',
                    'estado_trabajo',
                    'ocr',
                ])
                ->paginate($perPage, ['*'], 'page', $page);

            $data = $radicados->getCollection()->map(function ($radicado) use ($searchTerm) {
                $ocrResumen = $this->extractOcrContext($radicado->ocr, $searchTerm);
                $responsables = $radicado->responsables->map(function ($resp) {
                    $user = $resp->userCargo?->user;
                    return $user ? [
                        'id' => $user->id,
                        'nombre' => trim($user->nombres . ' ' . $user->apellidos),
                    ] : null;
                })->filter()->values();

                return [
                    'id' => $radicado->id,
                    'num_radicado' => $radicado->num_radicado,
                    'asunto' => $radicado->asunto,
                    'fec_docu' => $radicado->fec_docu,
                    'fec_venci' => $radicado->fec_venci,
                    'estado_trabajo' => $radicado->estado_trabajo,
                    'tercero' => $radicado->terceroEnviado ? [
                        'id' => $radicado->terceroEnviado->id,
                        'nom_razo_soci' => $radicado->terceroEnviado->nom_razo_soci,
                        'num_identific' => $radicado->terceroEnviado->num_docu_nit,
                    ] : null,
                    'clasificacion' => $radicado->clasificacionDocumental ? [
                        'id' => $radicado->clasificacionDocumental->id,
                        'nombre' => $radicado->clasificacionDocumental->nom,
                    ] : null,
                    'responsables' => $responsables,
                    'ocr_resumen' => $ocrResumen,
                ];
            });

            return $this->successResponse([
                'data' => $data,
                'current_page' => $radicados->currentPage(),
                'last_page' => $radicados->lastPage(),
                'per_page' => $radicados->perPage(),
                'total' => $radicados->total(),
                'query' => $searchTerm,
            ], 'Búsqueda realizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error en la búsqueda', $e->getMessage(), 500);
        }
    }

    private function prepareFullTextQuery(string $query): string
    {
        $words = explode(' ', trim($query));
        $prepared = array_map(function ($word) {
            $word = trim($word);
            if (strlen($word) >= 3) {
                return '+' . $word . '*';
            }
            return $word;
        }, $words);

        return implode(' ', array_filter($prepared));
    }

    private function extractOcrContext(?string $ocr, string $query, int $contextLength = 200): ?string
    {
        if (empty($ocr)) {
            return null;
        }

        $ocr = strtolower($ocr);
        $search = strtolower($query);
        $pos = strpos($ocr, $search);

        if ($pos === false) {
            return null;
        }

        $start = max(0, $pos - $contextLength);
        $end = min(strlen($ocr), $pos + strlen($search) + $contextLength);

        $context = substr($ocr, $start, $end - $start);

        if ($start > 0) {
            $context = '...' . $context;
        }
        if ($end < strlen($ocr)) {
            $context = $context . '...';
        }

        return $context;
    }
}
