<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Helpers\ArchivoHelper;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\VentanillaRadicaEnviadosRequest;
use App\Http\Requests\Ventanilla\ListRadicadosEnviadosRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosRespona;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaEnviadosController extends Controller
{
    use ApiResponseTrait;

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
                $query->where('tercero_enviado_id', $request->tercero_enviado_id);
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

                $radicado->clasificacion_documental = $clasifInfo;
                $radicado->responsables = $responsablesInfo['responsables'];
                $radicado->total_responsables = $responsablesInfo['total_responsables'];
                $radicado->total_custodios = $responsablesInfo['total_custodios'];
                $radicado->tiene_archivo_digital = !empty($radicado->archivo_digital);
                return $radicado;
            });

            return $this->successResponse($radicados, 'Listado de radicados enviados obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de radicados enviados', $e->getMessage(), 500);
        }
    }

    public function store(VentanillaRadicaEnviadosRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $numRadicado = $this->generarNumeroRadicado();

            $radicado = new VentanillaRadicaEnviados($validatedData);
            $radicado->num_radicado = $numRadicado;
            $radicado->save();

            DB::commit();

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
                'firmas.userCargo',
                'proyectores.userCargo',
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

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

            $data = $radicado->toArray();
            $data['clasificacion_documental'] = $radicado->getClasificacionDocumentalInfo();
            $data['documentos'] = $documentos;
            $data['usuario_creo_radicado'] = $radicado->getInfoUsuarioCrea();
            $data['usuario_subio'] = $radicado->getInfoUsuarioSubio();
            $data['responsables'] = $responsablesInfo['responsables'];
            $data['total_responsables'] = $responsablesInfo['total_responsables'];
            $data['total_custodios'] = $responsablesInfo['total_custodios'];

            return $this->successResponse($data, 'Radicado enviado encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el radicado enviado', $e->getMessage(), 500);
        }
    }

    public function update($id, VentanillaRadicaEnviadosRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $radicado->update($request->validated());

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
            $radicado->delete();

            DB::commit();

            return $this->successResponse(null, 'Radicado enviado eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el radicado enviado', $e->getMessage(), 500);
        }
    }

    public function estadisticas()
    {
        try {
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

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }

    public function listarRadicados(ListRadicadosEnviadosRequest $request)
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
                'respuestas.userCargo.user',
                'firmas.userCargo.user',
                'proyectores.userCargo.user',
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $eventos = [];

            $eventos[] = [
                'fecha' => $radicado->created_at,
                'tipo' => 'radicado_creado',
                'titulo' => 'Radicado creado',
                'descripcion' => 'Se creó el radicado ' . $radicado->num_radicado,
                'usuario' => $radicado->usuarioCreaRadicado?->getInfoUsuario(),
                'datos' => ['num_radicado' => $radicado->num_radicado, 'radicado_id' => $radicado->id],
            ];

            if ($radicado->updated_at->gt($radicado->created_at)) {
                $eventos[] = [
                    'fecha' => $radicado->updated_at,
                    'tipo' => 'radicado_actualizado',
                    'titulo' => 'Radicado actualizado',
                    'descripcion' => 'Se actualizaron datos del radicado',
                    'usuario' => null,
                    'datos' => [
                        'asunto' => $radicado->asunto,
                        'fec_docu' => $radicado->fec_docu?->toDateString(),
                        'clasifica_documen_id' => $radicado->clasifica_documen_id,
                    ],
                ];
            }

            if (!empty($radicado->archivo_digital)) {
                $eventos[] = [
                    'fecha' => $radicado->updated_at,
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
                    'fecha' => $responsable->created_at,
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

            foreach ($radicado->responsables as $responsable) {
                if ($responsable->fechor_visto) {
                    $user = $responsable->userCargo?->user?->getInfoUsuario();
                    $cargo = $responsable->userCargo?->cargo;
                    $eventos[] = [
                        'fecha' => $responsable->fechor_visto,
                        'tipo' => 'documento_visto',
                        'titulo' => 'Documento visualizado',
                        'descripcion' => $cargo ? 'Visualizado por: ' . $cargo->nom_organico : 'Un responsable visualizó el documento',
                        'usuario' => $user,
                        'datos' => ['responsable_id' => $responsable->id, 'cargo_nombre' => $cargo?->nom_organico],
                    ];
                }
            }

            foreach ($radicado->respuestas as $respuesta) {
                $user = $respuesta->userCargo?->user?->getInfoUsuario();
                $cargo = $respuesta->userCargo?->cargo;
                $eventos[] = [
                    'fecha' => $respuesta->created_at,
                    'tipo' => 'respuesta_asignada',
                    'titulo' => 'Responsable de respuesta',
                    'descripcion' => $cargo ? 'Asignado: ' . $cargo->nom_organico : 'Se asignó responsable de respuesta',
                    'usuario' => $user,
                    'datos' => ['respuesta_id' => $respuesta->id],
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

            usort($eventos, fn ($a, $b) => $b['fecha']->getTimestamp() - $a['fecha']->getTimestamp());

            $lineaTiempo = array_map(function ($e) {
                $e['fecha'] = $e['fecha']->toIso8601String();
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
                    'created_at' => $radicado->created_at->toIso8601String(),
                    'updated_at' => $radicado->updated_at->toIso8601String(),
                    'fec_docu' => $radicado->fec_docu?->toDateString(),
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
            ])->findOrFail($id);

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
}
