<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\VentanillaRadicaReciRequest;
use App\Http\Requests\Ventanilla\ListRadicadosRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\RadicadoNotification;
use Illuminate\Support\Facades\Mail;

class VentanillaRadicaReciController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de radicaciones recibidas con información detallada.
     *
     * Este método retorna todas las radicaciones recibidas con información
     * relacionada como clasificación documental, terceros, medios de recepción
     * y servidores de archivos.
     *
     * @param ListRadicadosRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de radicaciones
     *
     * @queryParam search string Buscar por número de radicado o asunto. Example: "2024-001"
     * @queryParam fecha_desde string Filtrar desde fecha (YYYY-MM-DD). Example: "2024-01-01"
     * @queryParam fecha_hasta string Filtrar hasta fecha (YYYY-MM-DD). Example: "2024-12-31"
     * @queryParam clasifica_documen_id integer Filtrar por clasificación documental. Example: 1
     * @queryParam tercero_id integer Filtrar por tercero. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 10). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de radicaciones obtenido exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "num_radicado": "20240101-00001",
     *         "dias_para_vencer": 5,
     *         "tiene_archivos": true,
     *         "created_at": "2024-01-01 10:00:00",
     *         "fec_venci": "2024-01-15",
     *         "clasificacion_documental": {
     *           "id": 1,
     *           "codigo": "01",
     *           "nombre": "Correspondencia"
     *         },
     *         "tercero": {
     *           "id": 1,
     *           "nombre": "Empresa ABC"
     *         }
     *       }
     *     ],
     *     "total": 100
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de radicaciones",
     *   "error": "Error message"
     * }
     */
    public function index(ListRadicadosRequest $request)
    {
        try {
            $query = VentanillaRadicaReci::with([
                'clasificacionDocumental.parent.parent', // Solo devolver id, tipo, cod y nom de la clasificación documental
                'tercero',
                'medioRecepcion:id,nombre', // Solo devolver id y nombre del medio de recepción
                'servidorArchivos',
                'usuariosResponsables.user', // Relación user necesaria para getDetalleCompleto()
                'usuariosResponsables.cargo.parent.parent' // Cargar toda la jerarquía del cargo para getJerarquiaCompleta()
            ])
                ->select([
                    'id',
                    'num_radicado',
                    'created_at',
                    'fec_venci',
                    'archivo_digital',
                    'asunto',
                    'clasifica_documen_id',
                    'tercero_id',
                    'medio_recep_id',
                    'config_server_id'
                ]);

            // Aplicar filtros si se proporcionan
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

            if ($request->filled('tercero_id')) {
                $query->where('tercero_id', $request->tercero_id);
            }

            // Ordenar por fecha de creación (más recientes primero)
            $query->orderBy('created_at', 'desc');

            // Paginar
            $perPage = $request->get('per_page', 10);
            $radicados = $query->paginate($perPage);

            // Transformar los datos para incluir los nuevos campos usando métodos de los modelos
            $radicados->getCollection()->transform(function ($radicado) {
                return $radicado;
            });

            return $this->successResponse($radicados, 'Listado de radicaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de radicaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva radicación recibida en el sistema.
     *
     * Este método permite crear una nueva radicación con validación de datos
     * y generación automática del número de radicado.
     *
     * @param VentanillaRadicaReciRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la radicación creada
     *
     * @bodyParam clasifica_documen_id integer required ID de la clasificación documental. Example: 1
     * @bodyParam tercero_id integer required ID del tercero. Example: 1
     * @bodyParam medio_recep_id integer required ID del medio de recepción. Example: 1
     * @bodyParam config_server_id integer ID del servidor de archivos. Example: 1
     * @bodyParam usuario_crea integer ID del usuario que crea el radicado (se asigna automáticamente). Example: 1
     * @bodyParam uploaded_by integer ID del usuario que sube el archivo (se asigna automáticamente). Example: 1
     * @bodyParam fec_venci string Fecha de vencimiento (YYYY-MM-DD). Example: "2024-01-15"
     * @bodyParam num_folios integer required Número de folios. Example: 5
     * @bodyParam num_anexos integer required Número de anexos. Example: 2
     * @bodyParam descrip_anexos string Descripción de anexos. Example: "Documentos adicionales"
     * @bodyParam asunto string Asunto del documento. Example: "Solicitud de información"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Radicación creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "num_radicado": "20240101-00001",
     *     "clasifica_documen_id": 1,
     *     "tercero_id": 1,
     *     "usuario_crea": 1,
     *     "created_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "clasifica_documen_id": ["La clasificación documental es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la radicación",
     *   "error": "Error message"
     * }
     */
    public function store(VentanillaRadicaReciRequest $request)
    {
        try {
            DB::beginTransaction();

            // Validar la solicitud
            $validatedData = $request->validated();

            // Generar el número de radicado (sin dependencia específica por ahora)
            $num_radicado = $this->generarNumeroRadicado();

            // Crear el radicado con los datos enviados
            $radicado = new VentanillaRadicaReci($validatedData);
            $radicado->num_radicado = $num_radicado;
            $radicado->usuario_crea = auth()->id(); // Asignar usuario que crea el radicado

            // Guardar el radicado
            $radicado->save();

            DB::commit();

            return $this->successResponse(
                $radicado->load(['clasificacionDocumental', 'tercero', 'medioRecepcion']),
                'Radicación creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una radicación específica por su ID.
     *
     * Este método permite obtener los detalles completos de una radicación
     * incluyendo todas sus relaciones.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la radicación
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicación encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "num_radicado": "20240101-00001",
     *     "clasificacion_documental": {
     *       "id": 1,
     *       "codigo": "01",
     *       "nombre": "Correspondencia"
     *     },
     *     "tercero": {
     *       "id": 1,
     *       "nombre": "Empresa ABC"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la radicación",
     *   "error": "Error message"
     * }
     */
    public function show($id)
    {
        try {
            $radicado = VentanillaRadicaReci::with([
                'clasificacionDocumental',
                'tercero',
                'medioRecepcion',
                'servidorArchivos'
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            return $this->successResponse($radicado, 'Radicación encontrada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una radicación existente en el sistema.
     *
     * Este método permite modificar los datos de una radicación existente
     * manteniendo el número de radicado original.
     *
     * @param int $id ID de la radicación
     * @param VentanillaRadicaReciRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la radicación actualizada
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     * @bodyParam clasifica_documen_id integer ID de la clasificación documental. Example: 1
     * @bodyParam tercero_id integer ID del tercero. Example: 1
     * @bodyParam medio_recep_id integer ID del medio de recepción. Example: 1
     * @bodyParam config_server_id integer ID del servidor de archivos. Example: 1
     * @bodyParam fec_venci string Fecha de vencimiento (YYYY-MM-DD). Example: "2024-01-15"
     * @bodyParam num_folios integer Número de folios. Example: 5
     * @bodyParam num_anexos integer Número de anexos. Example: 2
     * @bodyParam descrip_anexos string Descripción de anexos. Example: "Documentos adicionales"
     * @bodyParam asunto string Asunto del documento. Example: "Solicitud de información"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicación actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "num_radicado": "20240101-00001",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "clasifica_documen_id": ["La clasificación documental es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la radicación",
     *   "error": "Error message"
     * }
     */
    public function update($id, VentanillaRadicaReciRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $radicado->update($request->validated());

            DB::commit();

            return $this->successResponse(
                $radicado->load(['clasificacionDocumental', 'tercero', 'medioRecepcion']),
                'Radicación actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una radicación del sistema.
     *
     * Este método permite eliminar una radicación específica del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID de la radicación a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicación eliminada exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la radicación",
     *   "error": "Error message"
     * }
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $radicado->delete();

            DB::commit();

            return $this->successResponse(null, 'Radicación eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Genera un número de radicado único basado en la configuración del sistema.
     *
     * @param string|null $cod_dependencia Código de la dependencia (opcional)
     * @return string Número de radicado generado
     */
    private function generarNumeroRadicado($cod_dependencia = null)
    {
        $formato = ConfigVarias::getValor('formato_num_radicado_reci', 'YYYYMMDD-#####');

        // Contar la cantidad de '#' en el formato para definir la longitud del consecutivo
        preg_match('/#+/', $formato, $matches);
        $longitudConsecutivo = isset($matches[0]) ? strlen($matches[0]) : 5;

        // Obtener datos dinámicos
        $fecha = Carbon::now();
        $yyyy = $fecha->format('Y');
        $mm = $fecha->format('m');
        $dd = $fecha->format('d');

        // Obtener el último radicado del año actual y sumarle 1
        $ultimoRadicado = VentanillaRadicaReci::whereYear('created_at', $yyyy)
            ->orderBy('id', 'desc')
            ->value('num_radicado');

        // Extraer el número y sumarle 1
        preg_match('/\d+$/', $ultimoRadicado, $consecutivoAnterior);
        $nuevoConsecutivo = isset($consecutivoAnterior[0]) ? intval($consecutivoAnterior[0]) + 1 : 1;

        $consecutivo = str_pad($nuevoConsecutivo, $longitudConsecutivo, '0', STR_PAD_LEFT);

        // Reemplazar solo las variables que existan en el formato
        $variables = [
            'YYYY' => $yyyy,
            'MM' => $mm,
            'DD' => $dd,
            'COD_DEPEN' => $cod_dependencia,
            str_repeat('#', $longitudConsecutivo) => $consecutivo
        ];

        foreach ($variables as $key => $value) {
            if (strpos($formato, $key) !== false) {
                $formato = str_replace($key, $value, $formato);
            }
        }

        return $formato;
    }

    /**
     * Obtiene la dependencia del custodio desde los responsables.
     *
     * @param array $responsables Array de responsables
     * @return string|null Código de la dependencia
     */
    private function obtenerDependenciaCustodio($responsables)
    {
        foreach ($responsables as $responsable) {
            if (!empty($responsable['custodio']) && $responsable['custodio'] == true) {
                $usuario = User::find($responsable['user_id']);

                if ($usuario && $usuario->cargoActivo()->exists()) {
                    return $usuario->cargoActivo->first()->cod_organico; // Código de la dependencia
                }
            }
        }
        return null;
    }

    /**
     * Lista radicaciones con filtros avanzados para administración.
     *
     * Este método permite a los administradores listar radicaciones
     * con filtros avanzados y requiere autorización específica.
     *
     * @param ListRadicadosRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las radicaciones
     *
     * @queryParam estado integer Filtrar por estado. Example: 1
     * @queryParam fecha_desde string Filtrar desde fecha (YYYY-MM-DD). Example: "2024-01-01"
     * @queryParam fecha_hasta string Filtrar hasta fecha (YYYY-MM-DD). Example: "2024-12-31"
     * @queryParam usuario_responsable integer Filtrar por usuario responsable. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 10). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicaciones obtenidas exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [...],
     *     "total": 100
     *   }
     * }
     *
     * @response 403 {
     *   "status": false,
     *   "message": "No tiene permisos para ver radicaciones"
     * }
     */
    public function listarRadicados(ListRadicadosRequest $request)
    {
        try {
            $this->authorize('ver-radicados');

            $query = VentanillaRadicaReci::with([
                'clasificacionDocumental',
                'tercero',
                'medioRecepcion'
            ]);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $query->whereBetween('created_at', [$request->fecha_desde, $request->fecha_hasta]);
            }

            if ($request->filled('usuario_responsable')) {
                $query->whereHas('responsables', function ($q) use ($request) {
                    $q->where('users.id', $request->usuario_responsable);
                });
            }

            $perPage = $request->get('per_page', 10);
            $radicados = $query->paginate($perPage);

            return $this->successResponse($radicados, 'Radicaciones obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las radicaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas generales de las radicaciones recibidas.
     *
     * Este método proporciona estadísticas detalladas sobre las radicaciones
     * incluyendo totales por estado (pendientes, en proceso, finalizados),
     * radicaciones con archivos, radicaciones faltantes, y radicaciones próximas a vencer.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_radicados": 150,
     *     "total_pendientes": 20,
     *     "total_proceso": 80,
     *     "total_finalizados": 50,
     *     "total_con_archivos": 125,
     *     "faltan_archivo_digital": 25,
     *     "faltan_imprimir_rotulo": 30,
     *     "proximos_a_vencer": {
     *       "8_dias": 5,
     *       "5_dias": 8,
     *       "3_dias": 3
     *     },
     *     "radicados_vencidos": 12,
     *     "radicados_hoy": 5,
     *     "radicados_esta_semana": 15,
     *     "radicados_este_mes": 45,
     *     "porcentaje_con_archivo": 83.33,
     *     "porcentaje_rotulos_impresos": 80.0
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas()
    {
        try {
            $fechaActual = Carbon::now()->format('Y-m-d');
            $fecha8Dias = Carbon::now()->addDays(8)->format('Y-m-d');
            $fecha5Dias = Carbon::now()->addDays(5)->format('Y-m-d');
            $fecha3Dias = Carbon::now()->addDays(3)->format('Y-m-d');

            // Total de radicados
            $totalRadicados = VentanillaRadicaReci::count();

            // Radicados que faltan archivo digital (archivo_digital es null o vacío)
            $faltanArchivoDigital = VentanillaRadicaReci::where(function ($query) {
                $query->whereNull('archivo_digital')
                    ->orWhere('archivo_digital', '');
            })->count();

            // Radicados que faltan imprimir rótulo (asumimos que hay un campo para esto)
            // Si no existe el campo, podemos usar otro criterio o crear uno
            $faltanImprimirRotulo = VentanillaRadicaReci::where('impri_rotulo', '!=', 1)->count();

            // Radicados próximos a vencer
            $proximosAVencer = [
                '8_dias' => VentanillaRadicaReci::where('fec_venci', $fecha8Dias)->count(),
                '5_dias' => VentanillaRadicaReci::where('fec_venci', $fecha5Dias)->count(),
                '3_dias' => VentanillaRadicaReci::where('fec_venci', $fecha3Dias)->count(),
            ];

            // Radicados ya vencidos
            $radicadosVencidos = VentanillaRadicaReci::where('fec_venci', '<', $fechaActual)->count();

            // Radicados creados hoy
            $radicadosHoy = VentanillaRadicaReci::whereDate('created_at', $fechaActual)->count();

            // Radicados de la semana actual
            $radicadosEstaSemana = VentanillaRadicaReci::whereBetween('created_at', [
                Carbon::now()->startOfWeek()->format('Y-m-d'),
                Carbon::now()->endOfWeek()->format('Y-m-d')
            ])->count();

            // Radicados del mes actual
            $radicadosEsteMes = VentanillaRadicaReci::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();

            // Nuevas estadísticas solicitadas
            $totalConArchivos = $totalRadicados - $faltanArchivoDigital;

            // Calcular estados basados en la lógica del negocio:
            // - Pendientes: Radicados sin archivos y sin responsables
            // - En proceso: Radicados con archivos pero sin finalizar
            // - Finalizados: Radicados completos (con archivos y responsables)

            $totalPendientes = VentanillaRadicaReci::where(function ($query) {
                $query->whereNull('archivo_digital')
                    ->orWhere('archivo_digital', '');
            })->whereDoesntHave('responsables')->count();

            $totalEnProceso = VentanillaRadicaReci::whereNotNull('archivo_digital')
                ->where('archivo_digital', '!=', '')
                ->whereHas('responsables')
                ->count();

            $totalFinalizados = VentanillaRadicaReci::whereNotNull('archivo_digital')
                ->where('archivo_digital', '!=', '')
                ->whereHas('responsables')
                ->count(); // Por ahora igual que en proceso, se puede ajustar según criterios específicos

            $estadisticas = [
                'total_radicados' => $totalRadicados,
                'total_pendientes' => $totalPendientes,
                'total_proceso' => $totalEnProceso,
                'total_finalizados' => $totalFinalizados,
                'total_con_archivos' => $totalConArchivos,
                'faltan_archivo_digital' => $faltanArchivoDigital,
                'faltan_imprimir_rotulo' => $faltanImprimirRotulo,
                'proximos_a_vencer' => $proximosAVencer,
                'radicados_vencidos' => $radicadosVencidos,
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

    /**
     * Actualiza el asunto de una radicación específica.
     *
     * Este método permite modificar el asunto de una radicación solo si
     * ningún responsable ha visto el documento (fechor_visto es null).
     *
     * @param int $id ID de la radicación
     * @param Request $request La solicitud HTTP con el nuevo asunto
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el resultado
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     * @bodyParam asunto string required El nuevo asunto del documento. Example: "Nuevo asunto actualizado"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Asunto actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "asunto": "Nuevo asunto actualizado",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "No se puede editar el asunto porque al menos un responsable ya ha visto el documento"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "asunto": ["El asunto es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el asunto",
     *   "error": "Error message"
     * }
     */
    public function updateAsunto($id, Request $request)
    {
        try {
            DB::beginTransaction();

            // Validar la entrada
            $request->validate([
                'asunto' => 'required|string|max:300'
            ]);

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            // Verificar si algún responsable ya ha visto el documento
            $responsableVisto = $radicado->responsables()->whereNotNull('fechor_visto')->exists();

            if ($responsableVisto) {
                return $this->errorResponse(
                    'No se puede editar el asunto porque al menos un responsable ya ha visto el documento',
                    null,
                    400
                );
            }

            // Actualizar el asunto
            $radicado->update(['asunto' => $request->asunto]);

            DB::commit();

            return $this->successResponse(
                [
                    'id' => $radicado->id,
                    'asunto' => $radicado->asunto,
                    'updated_at' => $radicado->updated_at
                ],
                'Asunto actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el asunto', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza las fechas de un radicado (fecha de vencimiento y fecha del documento)
     * Solo permite la actualización si ningún responsable ha visto el documento
     *
     * @param int $id ID del radicado
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFechas(Request $request, $id)
    {
        try {
            // Buscar el radicado
            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return response()->json([
                    'status' => false,
                    'message' => 'Radicado no encontrado'
                ], 404);
            }

            // Verificar si algún responsable ya ha visto el documento
            $responsableVisto = VentanillaRadicaReciResponsa::where('radica_reci_id', $id)
                ->whereNotNull('fechor_visto')
                ->exists();

            if ($responsableVisto) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se pueden actualizar las fechas porque al menos un responsable ya ha visto el documento'
                ], 422);
            }

            // Validar los datos de entrada
            $validator = \Validator::make($request->all(), [
                'fec_venci' => 'nullable|date',
                'fec_docu' => 'nullable|date'
            ], [
                'fec_venci.date' => 'La fecha de vencimiento debe ser una fecha válida',
                'fec_docu.date' => 'La fecha del documento debe ser una fecha válida'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar solo los campos proporcionados
            $updateData = [];
            if ($request->has('fec_venci')) {
                $updateData['fec_venci'] = $request->fec_venci;
            }
            if ($request->has('fec_docu')) {
                $updateData['fec_docu'] = $request->fec_docu;
            }

            if (empty($updateData)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se proporcionaron fechas para actualizar'
                ], 422);
            }

            $radicado->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Fechas actualizadas exitosamente',
                'data' => [
                    'id' => $radicado->id,
                    'fec_venci' => $radicado->fec_venci,
                    'fec_docu' => $radicado->fec_docu,
                    'updated_at' => $radicado->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al actualizar las fechas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza la clasificación documental de una radicación recibida.
     * Solo permite la actualización si ningún responsable ha visto el documento.
     *
     * @param int $id ID de la radicación
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON
     */
    public function updateClasificacionDocumental($id, Request $request)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'clasifica_documen_id' => 'required|integer|exists:clasificacion_documental_trd,id'
            ], [
                'clasifica_documen_id.required' => 'La clasificación documental es obligatoria.',
                'clasifica_documen_id.integer' => 'La clasificación documental debe ser un número entero.',
                'clasifica_documen_id.exists' => 'La clasificación documental no es válida.'
            ]);

            // Buscar la radicación
            $radicacion = VentanillaRadicaReci::find($id);

            if (!$radicacion) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            // Verificar si algún responsable ha visto el documento
            $responsableHaVisto = VentanillaRadicaReciResponsa::where('radica_reci_id', $id)
                ->whereNotNull('fechor_visto')
                ->exists();

            if ($responsableHaVisto) {
                return $this->errorResponse(
                    'No se puede editar la clasificación documental porque al menos un responsable ya ha visto el documento',
                    null,
                    422
                );
            }

            // Actualizar la clasificación documental
            $radicacion->clasifica_documen_id = $request->clasifica_documen_id;
            $radicacion->save();

            return $this->successResponse(
                $radicacion->fresh(['clasificacionDocumental']),
                'Clasificación documental actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la clasificación documental', $e->getMessage(), 500);
        }
    }

    /**
     * Envía notificación por correo electrónico sobre un radicado.
     *
     * Este método permite enviar notificaciones por correo electrónico a los responsables
     * de un radicado, incluyendo información detallada del documento y archivos adjuntos.
     *
     * @param int $id ID del radicado
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando el envío
     *
     * @urlParam id integer required El ID del radicado. Example: 1
     * @bodyParam tipo string Tipo de notificación (asignacion, actualizacion, vencimiento). Example: "asignacion"
     * @bodyParam emails array Lista de correos electrónicos adicionales. Example: ["usuario@example.com"]
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Notificaciones enviadas exitosamente",
     *   "data": {
     *     "radicado_id": 1,
     *     "emails_enviados": ["responsable1@example.com", "responsable2@example.com"],
     *     "total_enviados": 2,
     *     "tipo_notificacion": "asignacion"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicado no encontrado"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "tipo": ["El tipo de notificación debe ser válido."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al enviar las notificaciones",
     *   "error": "Error message"
     * }
     */
    public function enviarNotificacion($id, Request $request)
    {
        try {
            // Validar la entrada
            $request->validate([
                'tipo' => 'sometimes|string|in:asignacion,actualizacion,vencimiento',
                'emails' => 'sometimes|array',
                'emails.*' => 'email'
            ]);

            $radicado = VentanillaRadicaReci::with([
                'responsables.userCargo.user',
                'clasificacionDocumental',
                'tercero'
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado no encontrado', null, 404);
            }

            $tipo = $request->get('tipo', 'asignacion');
            $emailsAdicionales = $request->get('emails', []);
            $emailsEnviados = [];

            // Obtener emails de los responsables
            foreach ($radicado->responsables as $responsable) {
                if ($responsable->userCargo && $responsable->userCargo->user && $responsable->userCargo->user->email) {
                    $email = $responsable->userCargo->user->email;
                    if (!in_array($email, $emailsEnviados)) {
                        Mail::to($email)->send(new RadicadoNotification($radicado, $tipo));
                        $emailsEnviados[] = $email;
                    }
                }
            }

            // Enviar a emails adicionales
            foreach ($emailsAdicionales as $email) {
                if (!in_array($email, $emailsEnviados)) {
                    Mail::to($email)->send(new RadicadoNotification($radicado, $tipo));
                    $emailsEnviados[] = $email;
                }
            }

            return $this->successResponse([
                'radicado_id' => $radicado->id,
                'emails_enviados' => $emailsEnviados,
                'total_enviados' => count($emailsEnviados),
                'tipo_notificacion' => $tipo
            ], 'Notificaciones enviadas exitosamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar las notificaciones', $e->getMessage(), 500);
        }
    }
}
