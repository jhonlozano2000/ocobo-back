<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Helpers\ArchivoHelper;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsa;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use App\Models\ControlAcceso\UserCargo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoController extends Controller
{
    use ApiResponseTrait;

    const PERM = 'Radicar -> Cores. Interna ->';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . ' Listar')->only(['index', 'estadisticas']);
        $this->middleware('can:' . self::PERM . ' Crear')->only(['store']);
        $this->middleware('can:' . self::PERM . ' Mostrar')->only(['show', 'lineaTiempo']);
        $this->middleware('can:' . self::PERM . ' Editar')->only(['update']);
        $this->middleware('can:' . self::PERM . ' Actualizar asunto')->only(['updateAsunto']);
        $this->middleware('can:' . self::PERM . ' Atualizar fechas de radicados')->only(['updateFechas']);
        $this->middleware('can:' . self::PERM . ' Actualizar clasificacion de radicados')->only(['updateClasificacionDocumental']);
        $this->middleware('can:' . self::PERM . ' Eliminar')->only(['destroy']);
        $this->middleware('can:' . self::PERM . ' Notificar Email')->only(['enviarNotificacion']);
    }

    public function index(Request $request)
    {
        try {
            $query = VentanillaRadicaInterno::query();

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

            $query->with([
                'clasificacionDocumental',
                'usuarioCrea',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
            ])->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $radicados = $query->paginate($perPage);

            $radicados->getCollection()->transform(function ($radicado) {
                $clasifInfo = $radicado->getClasificacionDocumentalInfo();
                $responsablesInfo = $radicado->getResponsablesInfo();

                $radicado->clasificacion_documental = $clasifInfo;
                $radicado->responsables = $responsablesInfo['responsables'];
                $radicado->total_responsables = $responsablesInfo['total_responsables'];
                $radicado->total_custodios = $responsablesInfo['total_custodios'];
                $radicado->tiene_archivo_digital = !empty($radicado->archivo_digital);
                $radicado->estado_trabajo_info = $radicado->getEstadoTrabajoInfo();
                $radicado->dias_para_vencer = $radicado->getDiasParaVencerAttribute();
                $radicado->is_vencida = $radicado->isVencida();
                return $radicado;
            });

            return $this->successResponse($radicados, 'Listado de radicados internos obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de radicados internos', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::with([
                'clasificacionDocumental',
                'usuarioCrea',
                'usuarioSubido',
                'destinatarios.userCargo.user',
                'destinatarios.userCargo.cargo',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
                'proyectores.userCargo.user',
                'proyectores.userCargo.cargo',
                'archivos.usuarioSubido',
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $radicado->actualizarEstadoTrabajo();
            $radicado->refresh();

            $radicado->loadClasificacionConJerarquia();

            $responsablesInfo = $radicado->getResponsablesInfo();

            $data = $radicado->toArray();
            $data['clasificacion_documental'] = $radicado->getClasificacionDocumentalInfo();
            $data['documentos'] = $radicado->getDocumentosRelacionados(true);
            $data['usuario_creo_radicado'] = $radicado->getInfoUsuarioCrea();
            $data['usuarioCreaRadicado'] = $radicado->getInfoUsuarioCrea();
            $data['responsables'] = $responsablesInfo['responsables'];
            $data['total_responsables'] = $responsablesInfo['total_responsables'];
            $data['total_custodios'] = $responsablesInfo['total_custodios'];
            $data['estado_trabajo_info'] = $radicado->getEstadoTrabajoInfo();
            $data['dias_para_vencer'] = $radicado->getDiasParaVencerAttribute();
            $data['is_vencida'] = $radicado->isVencida();
            $data['tiene_archivo_digital'] = $radicado->tieneArchivoDigital();
            $data['info_archivo_digital'] = $radicado->getInfoArchivoDigital();

            return $this->successResponse($data, 'Radicado interno encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el radicado interno', $e->getMessage(), 500);
        }
    }

    public function searchByOcr(Request $request)
    {
        $query = $request->get('search', '');

        if (strlen($query) < 3) {
            return $this->errorResponse('La búsqueda debe tener al menos 3 caracteres', null, 400);
        }

        $radicados = VentanillaRadicaInterno::where(function ($q) use ($query) {
                $q->where('num_radicado', 'like', "%{$query}%")
                  ->orWhere('asunto', 'like', "%{$query}%")
                  ->orWhere('ocr', 'like', "%{$query}%");
            })
            ->with(['clasificacionDocumental:id,nom,cod,tipo'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return $this->successResponse($radicados, 'Resultados de búsqueda OCR');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asunto' => 'required|string|max:1000',
            'clasifica_documen_id' => 'required|integer|exists:clasificacion_documental_trd,id',
            'num_folios' => 'required|integer|min:1',
            'num_anexos' => 'nullable|integer|min:0',
            'descrip_anexos' => 'nullable|string|max:500',
            'fec_venci' => 'nullable|date',
            'destinatarios' => 'required|array|min:1',
            'destinatarios.*' => 'integer|exists:users,id',
            'responsables' => 'required|array|min:1',
            'responsables.*.user_id' => 'required|integer|exists:users,id',
            'responsables.*.custodio' => 'required|boolean',
            'proyectores' => 'nullable|array',
            'proyectores.*' => 'integer|exists:users,id',
            'firmantes' => 'nullable|array',
            'firmantes.*' => 'integer|exists:users,id',
            'originadores' => 'nullable|array',
            'originadores.*' => 'integer|exists:users,id',
        ], [
            'asunto.required' => 'El asunto es obligatorio.',
            'asunto.max' => 'El asunto no puede exceder 1000 caracteres.',
            'clasifica_documen_id.required' => 'Debe seleccionar una clasificación documental (TRD).',
            'clasifica_documen_id.integer' => 'La clasificación documental es inválida.',
            'clasifica_documen_id.exists' => 'La clasificación documental no existe.',
            'num_folios.required' => 'El número de folios es obligatorio.',
            'num_folios.integer' => 'El número de folios debe ser un número entero.',
            'num_folios.min' => 'El número de folios debe ser al menos 1.',
            'destinatarios.required' => 'Debe asignar al menos un destinatario.',
            'destinatarios.array' => 'Los destinatarios deben ser una lista.',
            'destinatarios.min' => 'Debe asignar al menos un destinatario.',
            'destinatarios.*.integer' => 'El destinatario es inválido.',
            'destinatarios.*.exists' => 'El destinatario no existe.',
            'responsables.required' => 'Debe asignar al menos un responsable.',
            'responsables.array' => 'Los responsables deben ser una lista.',
            'responsables.*.user_id.required' => 'El responsable es obligatorio.',
            'responsables.*.user_id.integer' => 'El responsable es inválido.',
            'responsables.*.user_id.exists' => 'El responsable no existe.',
            'responsables.*.custodio.required' => 'Debe indicar si es custodio.',
            'responsables.*.custodio.boolean' => 'El valor de custodio es inválido.',
        ]);

        try {
            DB::beginTransaction();

            $radicado = new VentanillaRadicaInterno($request->only([
                "asunto", "clasifica_documen_id", "num_folios", "num_anexos", "descrip_anexos", "fec_venci"
            ]));
            
            $radicado->num_radicado = "INT-" . date("Ymd") . "-" . rand(100, 999);
            $radicado->usuario_crea = auth()->id();
            $radicado->save();

            // 2. Destinatarios - buscar users_cargos_id a partir de user_id
            if ($request->has("destinatarios")) {
                foreach ($request->destinatarios as $userId) {
                    $cargo = UserCargo::where('user_id', $userId)->where('estado', 1)->first();
                    if ($cargo) {
                        DB::table("ventanilla_radica_internos_destina")->insert([
                            "radica_interno_id" => $radicado->id,
                            "users_cargos_id" => $cargo->id,
                            "created_at" => now()
                        ]);
                    }
                }
            }

            // 3. Responsables - buscar users_cargos_id a partir de user_id
            if ($request->has("responsables")) {
                foreach ($request->responsables as $resp) {
                    $cargo = UserCargo::where('user_id', $resp["user_id"])->where('estado', 1)->first();
                    if ($cargo) {
                        DB::table("ventanilla_radica_interno_responsa")->insert([
                            "radica_interno_id" => $radicado->id,
                            "users_cargos_id" => $cargo->id,
                            "custodio" => $resp["custodio"],
                            "created_at" => now()
                        ]);
                    }
                }
            }

            // 4. Proyectores - buscar users_cargos_id a partir de user_id
            if ($request->has("proyectores")) {
                foreach ($request->proyectores as $userId) {
                    $cargo = UserCargo::where('user_id', $userId)->where('estado', 1)->first();
                    if ($cargo) {
                        DB::table("ventanilla_radica_interno_proyectores")->insert([
                            "radica_interno_id" => $radicado->id,
                            "users_cargos_id" => $cargo->id,
                            "created_at" => now()
                        ]);
                    }
                }
            }

            // 5. Firmantes
            if ($request->has("firmantes")) {
                foreach ($request->firmantes as $id) {
                    DB::table("ventanilla_radica_internos_firmantes")->insert([
                        "radica_interno_id" => $radicado->id,
                        "users_id" => $id,
                        "created_at" => now()
                    ]);
                }
            }

            DB::commit();
            Cache::forget('ventanilla_internos_estadisticas');
            return $this->successResponse($radicado, "Radicación interna completada exitosamente", 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse("Error al procesar la radicación interna", $e->getMessage(), 500);
        }
    }

    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $radicado->update($request->only([
                'asunto', 'clasifica_documen_id', 'num_folios', 'num_anexos', 'descrip_anexos', 'fec_venci'
            ]));

            DB::commit();
            Cache::forget('ventanilla_internos_estadisticas');

            return $this->successResponse(
                $radicado->fresh(['clasificacionDocumental', 'usuarioCrea', 'destinatarios.userCargo.user', 'responsables.userCargo.user', 'proyectores.userCargo.user']),
                'Radicado interno actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el radicado interno', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            if ($radicado->archivo_digital) {
                \App\Helpers\ArchivoHelper::eliminarArchivo($radicado->archivo_digital, 'ventanilla_radica_interno_archivos');
            }

            $radicado->destinatarios()->delete();
            $radicado->responsables()->delete();
            $radicado->proyectores()->delete();
            $radicado->delete();

            DB::commit();
            Cache::forget('ventanilla_internos_estadisticas');

            return $this->successResponse(null, 'Radicado interno eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el radicado interno', $e->getMessage(), 500);
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

            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $responsableHaVisto = \App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsa::where('radica_interno_id', $id)
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

    public function enviarNotificacion($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::with([
                'destinatarios.userCargo.user',
                'destinatarios.userCargo.cargo',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
                'clasificacionDocumental',
                'usuarioCrea',
            ])->findOrFail($id);

            $resultado = app(\App\Services\Notificaciones\NotificacionCorrespondenciaService::class)
                ->enviarRadicadoInterno($radicado);

            return $this->successResponse([
                'radicado_id' => $radicado->id,
                ...$resultado,
            ], 'Notificaciones enviadas exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Radicado interno no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar las notificaciones', $e->getMessage(), 500);
        }
    }

    public function lineaTiempo($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::with([
                'usuarioCrea',
                'destinatarios.userCargo.user',
                'destinatarios.userCargo.cargo',
                'responsables.userCargo.user',
                'responsables.userCargo.cargo',
                'proyectores.userCargo.user',
                'proyectores.userCargo.cargo',
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $eventos = [];

            $eventos[] = [
                'fecha' => $radicado->created_at,
                'tipo' => 'radicado_creado',
                'titulo' => 'Radicado creado',
                'descripcion' => 'Se creó el radicado interno ' . $radicado->num_radicado,
                'usuario' => $radicado->usuarioCrea ? trim($radicado->usuarioCrea->nombres . ' ' . $radicado->usuarioCrea->apellidos) : null,
                'datos' => ['num_radicado' => $radicado->num_radicado, 'radicado_id' => $radicado->id],
            ];

            if (!empty($radicado->archivo_digital)) {
                $eventos[] = [
                    'fecha' => $radicado->updated_at,
                    'tipo' => 'archivo_digital_subido',
                    'titulo' => 'Archivo digital subido',
                    'descripcion' => 'Se cargó el archivo digital: ' . basename($radicado->archivo_digital),
                    'usuario' => null,
                    'datos' => ['archivo_nombre' => basename($radicado->archivo_digital)],
                ];
            }

            foreach ($radicado->destinatarios as $destinatario) {
                $user = $destinatario->userCargo?->user;
                $cargo = $destinatario->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => $destinatario->created_at,
                    'tipo' => 'destinatario_asignado',
                    'titulo' => 'Destinatario asignado',
                    'descripcion' => $cargo ? 'Asignado: ' . $cargo->nom_organico : 'Se asignó destinatario',
                    'usuario' => $user ? trim($user->nombres . ' ' . $user->apellidos) : null,
                    'datos' => ['destinatario_id' => $destinatario->id],
                ];
            }

            foreach ($radicado->responsables as $responsable) {
                $user = $responsable->userCargo?->user;
                $cargo = $responsable->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => $responsable->created_at,
                    'tipo' => 'responsable_asignado',
                    'titulo' => 'Responsable asignado',
                    'descripcion' => $cargo ? 'Asignado: ' . $cargo->nom_organico . ($responsable->custodio ? ' (custodio)' : '') : 'Se asignó responsable',
                    'usuario' => $user ? trim($user->nombres . ' ' . $user->apellidos) : null,
                    'custodio' => $responsable->custodio,
                    'datos' => ['responsable_id' => $responsable->id],
                ];
            }

            foreach ($radicado->proyectores as $proyector) {
                $user = $proyector->userCargo?->user;
                $cargo = $proyector->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => $proyector->created_at,
                    'tipo' => 'proyector_asignado',
                    'titulo' => 'Proyector asignado',
                    'descripcion' => $cargo ? 'Asignado: ' . $cargo->nom_organico : 'Se asignó proyector',
                    'usuario' => $user ? trim($user->nombres . ' ' . $user->apellidos) : null,
                    'datos' => ['proyector_id' => $proyector->id],
                ];
            }

            usort($eventos, fn($a, $b) => $b['fecha']->getTimestamp() - $a['fecha']->getTimestamp());

            $lineaTiempo = array_map(function ($e) {
                $e['fecha'] = $e['fecha']->toIso8601String();
                return $e;
            }, $eventos);

            return $this->successResponse([
                'radicado' => [
                    'id' => $radicado->id,
                    'num_radicado' => $radicado->num_radicado,
                    'asunto' => $radicado->asunto,
                    'created_at' => $radicado->created_at->toIso8601String(),
                    'updated_at' => $radicado->updated_at->toIso8601String(),
                ],
                'eventos' => $lineaTiempo,
            ], 'Línea de tiempo obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la línea de tiempo', $e->getMessage(), 500);
        }
    }

    public function estadisticas()
    {
        try {
            $cacheKey = 'ventanilla_internos_estadisticas';
            $cached = Cache::get($cacheKey);

            if ($cached) {
                return $this->successResponse($cached, 'Estadísticas obtenidas exitosamente');
            }

            $estadoService = new RadicadoEstadoTrabajoService();
            $fechaActual = Carbon::now()->format('Y-m-d');

            $totalRadicados = VentanillaRadicaInterno::count();

            $faltanArchivoDigital = VentanillaRadicaInterno::where(function ($query) {
                $query->whereNull('archivo_digital')
                    ->orWhere('archivo_digital', '');
            })->count();

            $radicadosVencidos = VentanillaRadicaInterno::where('fec_venci', '<', $fechaActual)->count();

            $radicadosHoy = VentanillaRadicaInterno::whereDate('created_at', $fechaActual)->count();

            $radicadosEstaSemana = VentanillaRadicaInterno::whereBetween('created_at', [
                Carbon::now()->startOfWeek()->format('Y-m-d'),
                Carbon::now()->endOfWeek()->format('Y-m-d')
            ])->count();

            $radicadosEsteMes = VentanillaRadicaInterno::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();

            $totalConArchivos = $totalRadicados - $faltanArchivoDigital;

            $totalEnProceso = VentanillaRadicaInterno::where('estado_trabajo', $estadoService::ESTADO_EN_PROCESO)->count();
            $totalPorVencer = VentanillaRadicaInterno::where('estado_trabajo', $estadoService::ESTADO_POR_VENCER)->count();
            $totalVencido = VentanillaRadicaInterno::where('estado_trabajo', $estadoService::ESTADO_VENCIDO)->count();
            $totalFinalizado = VentanillaRadicaInterno::where('estado_trabajo', $estadoService::ESTADO_FINALIZADO)->count();

            $estadisticas = [
                'total_radicados' => $totalRadicados,
                'total_en_proceso' => $totalEnProceso,
                'total_por_vencer' => $totalPorVencer,
                'total_vencido' => $totalVencido,
                'total_finalizado' => $totalFinalizado,
                'total_con_archivos' => $totalConArchivos,
                'faltan_archivo_digital' => $faltanArchivoDigital,
                'radicados_vencidos' => $radicadosVencidos,
                'radicados_hoy' => $radicadosHoy,
                'radicados_esta_semana' => $radicadosEstaSemana,
                'radicados_este_mes' => $radicadosEsteMes,
                'porcentaje_con_archivo' => $totalRadicados > 0 ? round((($totalRadicados - $faltanArchivoDigital) / $totalRadicados) * 100, 2) : 0,
                'colores_estados' => $estadoService::getColoresPorEstado(),
            ];

            Cache::put($cacheKey, $estadisticas, now()->addMinutes(10));

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
