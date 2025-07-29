<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ClasificacionDocumental\StoreClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\UpdateClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\ImportarTRDRequest;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClasificacionDocumentalTRDController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene el listado de TRD (Tabla de Retención Documental) organizadas jerárquicamente.
     *
     * Este método retorna todas las series y subseries de TRD que no tienen padre,
     * organizadas en una estructura jerárquica con sus elementos hijos.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la estructura TRD
     *
     * @response 200 {
     *   "status": true,
     *   "message": "TRD obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "tipo": "Serie",
     *       "cod": "S001",
     *       "nom": "Gestión Administrativa",
     *       "children": [
     *         {
     *           "id": 2,
     *           "tipo": "SubSerie",
     *           "cod": "SS001",
     *           "nom": "Contratos",
     *           "children": []
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ClasificacionDocumentalTRD::whereIn('tipo', ['Serie', 'SubSerie'])
                ->whereNull('parent')
                ->with(['children', 'dependencia']);

            // Aplicar filtros si se proporcionan
            if ($request->filled('dependencia_id')) {
                $query->where('dependencia_id', $request->dependencia_id);
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            $trd = $query->orderBy('cod', 'asc')->get();

            return $this->successResponse($trd, 'TRD obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo elemento TRD en el sistema.
     *
     * Este método permite crear series, subseries o tipos de documento
     * con validaciones específicas según el tipo de elemento.
     *
     * @param StoreClasificacionDocumentalRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el elemento creado
     *
     * @bodyParam tipo string required Tipo de elemento (Serie, SubSerie, TipoDocumento). Example: "Serie"
     * @bodyParam cod string required Código único del elemento. Example: "S001"
     * @bodyParam nom string required Nombre del elemento. Example: "Gestión Administrativa"
     * @bodyParam parent integer ID del elemento padre (requerido para SubSerie y TipoDocumento). Example: 1
     * @bodyParam dependencia_id integer required ID de la dependencia. Example: 1
     * @bodyParam a_g string Años de gestión. Example: "5"
     * @bodyParam a_c string Años de centralización. Example: "10"
     * @bodyParam ct boolean Conservación total. Example: true
     * @bodyParam e boolean Eliminación. Example: false
     * @bodyParam m_d boolean Microfilmación digital. Example: false
     * @bodyParam s boolean Selección. Example: false
     * @bodyParam procedimiento string Procedimiento asociado. Example: "PROC-001"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Elemento TRD creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Serie",
     *     "cod": "S001",
     *     "nom": "Gestión Administrativa"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "parent": ["El campo parent es obligatorio para SubSerie y TipoDocumento."]
     *   }
     * }
     */
    public function store(StoreClasificacionDocumentalRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $validatedData['user_register'] = auth()->id();

            // Validar jerarquía según el tipo
            if (!$this->validarJerarquia($validatedData)) {
                return $this->errorResponse('Error de validación', [
                    'parent' => ['La jerarquía no es válida para el tipo especificado.']
                ], 422);
            }

            $trd = ClasificacionDocumentalTRD::create($validatedData);

            DB::commit();

            return $this->successResponse($trd->load('dependencia'), 'Elemento TRD creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el elemento TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un elemento TRD específico con su estructura jerárquica.
     *
     * @param int $id ID del elemento TRD
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el elemento
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elemento TRD obtenido exitosamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Serie",
     *     "cod": "S001",
     *     "nom": "Gestión Administrativa",
     *     "children": []
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Elemento TRD no encontrado"
     * }
     */
    public function show($id)
    {
        try {
            $trd = ClasificacionDocumentalTRD::with(['children', 'dependencia', 'parent'])
                ->find($id);

            if (!$trd) {
                return $this->errorResponse('Elemento TRD no encontrado', null, 404);
            }

            return $this->successResponse($trd, 'Elemento TRD obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el elemento TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un elemento TRD existente.
     *
     * @param UpdateClasificacionDocumentalRequest $request La solicitud HTTP validada
     * @param int $id ID del elemento TRD
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el elemento actualizado
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elemento TRD actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Serie",
     *     "cod": "S001",
     *     "nom": "Gestión Administrativa Actualizada"
     *   }
     * }
     */
    public function update(UpdateClasificacionDocumentalRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $trd = ClasificacionDocumentalTRD::find($id);

            if (!$trd) {
                return $this->errorResponse('Elemento TRD no encontrado', null, 404);
            }

            $validatedData = $request->validated();

            // Validar jerarquía si se está cambiando el parent
            if (isset($validatedData['parent']) && $validatedData['parent'] !== $trd->parent) {
                $validatedData['tipo'] = $validatedData['tipo'] ?? $trd->tipo;
                if (!$this->validarJerarquia($validatedData)) {
                    return $this->errorResponse('Error de validación', [
                        'parent' => ['La jerarquía no es válida para el tipo especificado.']
                    ], 422);
                }
            }

            $trd->update($validatedData);

            DB::commit();

            return $this->successResponse($trd->load('dependencia'), 'Elemento TRD actualizado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el elemento TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un elemento TRD del sistema.
     *
     * Solo permite eliminar elementos que no tengan hijos asociados.
     *
     * @param int $id ID del elemento TRD
     * @return \Illuminate\Http\JsonResponse Respuesta JSON
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elemento TRD eliminado exitosamente"
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "No se puede eliminar porque tiene elementos asociados"
     * }
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $trd = ClasificacionDocumentalTRD::find($id);

            if (!$trd) {
                return $this->errorResponse('Elemento TRD no encontrado', null, 404);
            }

            // Verificar si tiene hijos antes de eliminar
            if ($trd->children()->exists()) {
                return $this->errorResponse('No se puede eliminar porque tiene elementos asociados', null, 400);
            }

            $trd->delete();

            DB::commit();

            return $this->successResponse(null, 'Elemento TRD eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el elemento TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Importa una TRD desde un archivo Excel.
     *
     * Este método procesa un archivo Excel con la estructura TRD y crea
     * una nueva versión temporal para la dependencia especificada.
     *
     * @param ImportarTRDRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON
     *
     * @bodyParam file file required Archivo Excel con la estructura TRD. Example: "trd.xlsx"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "TRD importada correctamente y versión creada exitosamente"
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "La dependencia ya tiene una versión pendiente por aprobar"
     * }
     */
    public function importarTRD(ImportarTRDRequest $request)
    {
        try {
            DB::beginTransaction();

            // Procesar archivo
            $filePath = $this->procesarArchivoExcel($request);
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            // Obtener información de dependencia
            $dependencia = $this->obtenerDependencia($sheet);
            if (!$dependencia) {
                return $this->errorResponse('La dependencia especificada no existe en el sistema', null, 400);
            }

            // Verificar versión pendiente
            if ($this->tieneVersionPendiente($dependencia->id)) {
                return $this->errorResponse('La dependencia ya tiene una versión pendiente por aprobar', null, 400);
            }

            // Crear nueva versión
            $nuevaVersion = $this->crearNuevaVersion($dependencia->id);

            // Procesar datos TRD
            $this->procesarDatosTRD($data, $dependencia->id, $nuevaVersion->id);

            DB::commit();

            // Limpiar archivo temporal
            $this->limpiarArchivoTemporal($filePath);

            return $this->successResponse(null, 'TRD importada correctamente y versión creada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al importar la TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas de TRD por dependencia.
     *
     * @param int $id ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_series": 10,
     *     "total_subseries": 25,
     *     "total_tipos_documento": 100
     *   }
     * }
     */
    public function estadistica($id)
    {
        try {
            $estadisticas = ClasificacionDocumentalTRD::where('dependencia_id', $id)
                ->selectRaw('tipo, COUNT(*) as total')
                ->groupBy('tipo')
                ->pluck('total', 'tipo')
                ->toArray();

            $data = [
                'total_series' => $estadisticas['Serie'] ?? 0,
                'total_subseries' => $estadisticas['SubSerie'] ?? 0,
                'total_tipos_documento' => $estadisticas['TipoDocumento'] ?? 0,
                'total_elementos' => array_sum($estadisticas)
            ];

            return $this->successResponse($data, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Lista TRD por dependencia específica.
     *
     * @param int $id ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con TRD de la dependencia
     *
     * @response 200 {
     *   "status": true,
     *   "message": "TRD de dependencia obtenidas exitosamente",
     *   "data": []
     * }
     */
    public function listarPorDependencia($id)
    {
        try {
            $trd = ClasificacionDocumentalTRD::where('dependencia_id', $id)
                ->whereNull('parent')
                ->with(['children', 'dependencia'])
                ->orderBy('cod', 'asc')
                ->get();

            return $this->successResponse($trd, 'TRD de dependencia obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener TRD de dependencia', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas totales del sistema TRD.
     *
     * Este método retorna estadísticas generales de todas las TRD
     * del sistema, incluyendo totales por tipo y dependencias.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas totales
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas totales obtenidas exitosamente",
     *   "data": {
     *     "total_elementos": 150,
     *     "total_series": 25,
     *     "total_subseries": 75,
     *     "total_tipos_documento": 50,
     *     "total_dependencias": 10,
     *     "elementos_por_dependencia": {
     *       "promedio": 15.0,
     *       "maxima": 30,
     *       "minima": 5
     *     },
     *     "distribucion_por_tipo": {
     *       "Serie": 16.67,
     *       "SubSerie": 50.0,
     *       "TipoDocumento": 33.33
     *     }
     *   }
     * }
     */
    public function estadisticasTotales()
    {
        try {
            // Estadísticas generales
            $totalElementos = ClasificacionDocumentalTRD::count();
            $totalSeries = ClasificacionDocumentalTRD::where('tipo', 'Serie')->count();
            $totalSubSeries = ClasificacionDocumentalTRD::where('tipo', 'SubSerie')->count();
            $totalTiposDocumento = ClasificacionDocumentalTRD::where('tipo', 'TipoDocumento')->count();
            $totalDependencias = ClasificacionDocumentalTRD::distinct('dependencia_id')->count();

            // Estadísticas por dependencia
            $elementosPorDependencia = ClasificacionDocumentalTRD::selectRaw('
                dependencia_id,
                COUNT(*) as total_elementos,
                SUM(CASE WHEN tipo = "Serie" THEN 1 ELSE 0 END) as series,
                SUM(CASE WHEN tipo = "SubSerie" THEN 1 ELSE 0 END) as subseries,
                SUM(CASE WHEN tipo = "TipoDocumento" THEN 1 ELSE 0 END) as tipos_documento
            ')
                ->groupBy('dependencia_id')
                ->with('dependencia:id,nom_organico,cod_organico')
                ->get();

            // Calcular promedios y extremos
            $totales = $elementosPorDependencia->pluck('total_elementos');
            $promedio = $totales->avg();
            $maxima = $totales->max();
            $minima = $totales->min();

            // Calcular distribución porcentual por tipo
            $distribucionPorTipo = [];
            if ($totalElementos > 0) {
                $distribucionPorTipo = [
                    'Serie' => round(($totalSeries / $totalElementos) * 100, 2),
                    'SubSerie' => round(($totalSubSeries / $totalElementos) * 100, 2),
                    'TipoDocumento' => round(($totalTiposDocumento / $totalElementos) * 100, 2)
                ];
            }

            // Obtener dependencias más activas
            $dependenciasMasActivas = $elementosPorDependencia
                ->sortByDesc('total_elementos')
                ->take(5)
                ->map(function ($item) {
                    return [
                        'dependencia' => $item->dependencia->nom_organico ?? 'N/A',
                        'codigo' => $item->dependencia->cod_organico ?? 'N/A',
                        'total_elementos' => $item->total_elementos,
                        'series' => $item->series,
                        'subseries' => $item->subseries,
                        'tipos_documento' => $item->tipos_documento
                    ];
                });

            $data = [
                'total_elementos' => $totalElementos,
                'total_series' => $totalSeries,
                'total_subseries' => $totalSubSeries,
                'total_tipos_documento' => $totalTiposDocumento,
                'total_dependencias' => $totalDependencias,
                'elementos_por_dependencia' => [
                    'promedio' => round($promedio, 2),
                    'maxima' => $maxima,
                    'minima' => $minima
                ],
                'distribucion_por_tipo' => $distribucionPorTipo,
                'dependencias_mas_activas' => $dependenciasMasActivas,
                'fecha_actualizacion' => now()->format('Y-m-d H:i:s')
            ];

            return $this->successResponse($data, 'Estadísticas totales obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas totales', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas detalladas por dependencias.
     *
     * Este método retorna estadísticas específicas de cada dependencia
     * que tiene elementos TRD registrados.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas por dependencias
     *
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     * @queryParam sort string Ordenar por campo (total_elementos, series, subseries, tipos_documento). Example: "total_elementos"
     * @queryParam order string Orden ascendente o descendente (asc, desc). Example: "desc"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas por dependencias obtenidas exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "dependencia": {
     *           "id": 1,
     *           "nom_organico": "Secretaría General",
     *           "cod_organico": "SG001"
     *         },
     *         "total_elementos": 25,
     *         "series": 5,
     *         "subseries": 15,
     *         "tipos_documento": 5,
     *         "porcentaje_series": 20.0,
     *         "porcentaje_subseries": 60.0,
     *         "porcentaje_tipos_documento": 20.0
     *       }
     *     ],
     *     "resumen": {
     *       "total_dependencias": 10,
     *       "promedio_elementos": 15.5,
     *       "dependencia_mas_activa": "Secretaría General",
     *       "dependencia_menos_activa": "Recursos Humanos"
     *     }
     *   }
     * }
     */
    public function estadisticasPorDependencias(Request $request)
    {
        try {
            $query = ClasificacionDocumentalTRD::selectRaw('
                dependencia_id,
                COUNT(*) as total_elementos,
                SUM(CASE WHEN tipo = "Serie" THEN 1 ELSE 0 END) as series,
                SUM(CASE WHEN tipo = "SubSerie" THEN 1 ELSE 0 END) as subseries,
                SUM(CASE WHEN tipo = "TipoDocumento" THEN 1 ELSE 0 END) as tipos_documento
            ')
                ->groupBy('dependencia_id')
                ->with('dependencia:id,nom_organico,cod_organico');

            // Aplicar ordenamiento
            $sortField = $request->get('sort', 'total_elementos');
            $sortOrder = $request->get('order', 'desc');

            if (in_array($sortField, ['total_elementos', 'series', 'subseries', 'tipos_documento'])) {
                $query->orderBy($sortField, $sortOrder);
            }

            // Paginar resultados
            $perPage = $request->get('per_page', 15);
            $estadisticas = $query->paginate($perPage);

            // Procesar datos para incluir porcentajes
            $estadisticas->getCollection()->transform(function ($item) {
                $total = $item->total_elementos;

                $item->porcentaje_series = $total > 0 ? round(($item->series / $total) * 100, 2) : 0;
                $item->porcentaje_subseries = $total > 0 ? round(($item->subseries / $total) * 100, 2) : 0;
                $item->porcentaje_tipos_documento = $total > 0 ? round(($item->tipos_documento / $total) * 100, 2) : 0;

                return $item;
            });

            // Calcular resumen general
            $todasLasEstadisticas = ClasificacionDocumentalTRD::selectRaw('
                dependencia_id,
                COUNT(*) as total_elementos
            ')
                ->groupBy('dependencia_id')
                ->with('dependencia:id,nom_organico')
                ->get();

            $resumen = [
                'total_dependencias' => $todasLasEstadisticas->count(),
                'promedio_elementos' => $todasLasEstadisticas->avg('total_elementos'),
                'dependencia_mas_activa' => $todasLasEstadisticas->sortByDesc('total_elementos')->first()?->dependencia->nom_organico ?? 'N/A',
                'dependencia_menos_activa' => $todasLasEstadisticas->sortBy('total_elementos')->first()?->dependencia->nom_organico ?? 'N/A',
                'total_elementos_sistema' => $todasLasEstadisticas->sum('total_elementos')
            ];

            $data = [
                'estadisticas' => $estadisticas,
                'resumen' => $resumen
            ];

            return $this->successResponse($data, 'Estadísticas por dependencias obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas por dependencias', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas comparativas entre dependencias.
     *
     * Este método retorna un análisis comparativo de las dependencias
     * incluyendo rankings, promedios y métricas de rendimiento.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas comparativas
     *
     * @queryParam limit integer Número de dependencias a incluir (por defecto: 10). Example: 5
     * @queryParam tipo string Filtrar por tipo específico (Serie, SubSerie, TipoDocumento). Example: "Serie"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas comparativas obtenidas exitosamente",
     *   "data": {
     *     "ranking_dependencias": [
     *       {
     *         "posicion": 1,
     *         "dependencia": "Secretaría General",
     *         "codigo": "SG001",
     *         "total_elementos": 30,
     *         "puntuacion": 100.0
     *       }
     *     ],
     *     "metricas_generales": {
     *       "promedio_elementos": 15.5,
     *       "mediana_elementos": 12.0,
     *       "desviacion_estandar": 8.2
     *     },
     *     "distribucion_por_tipo": {
     *       "Serie": {
     *         "promedio": 2.5,
     *         "maxima": 8,
     *         "minima": 0
     *       }
     *     }
     *   }
     * }
     */
    public function estadisticasComparativas(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $tipo = $request->get('tipo');

            // Consulta base
            $query = ClasificacionDocumentalTRD::selectRaw('
                dependencia_id,
                COUNT(*) as total_elementos,
                SUM(CASE WHEN tipo = "Serie" THEN 1 ELSE 0 END) as series,
                SUM(CASE WHEN tipo = "SubSerie" THEN 1 ELSE 0 END) as subseries,
                SUM(CASE WHEN tipo = "TipoDocumento" THEN 1 ELSE 0 END) as tipos_documento
            ')
                ->groupBy('dependencia_id')
                ->with('dependencia:id,nom_organico,cod_organico');

            // Filtrar por tipo si se especifica
            if ($tipo && in_array($tipo, ['Serie', 'SubSerie', 'TipoDocumento'])) {
                $query->where('tipo', $tipo);
            }

            $estadisticas = $query->get();

            // Calcular métricas generales
            $totales = $estadisticas->pluck('total_elementos');
            $promedio = $totales->avg();
            $mediana = $this->calcularMediana($totales->toArray());
            $desviacion = $this->calcularDesviacionEstandar($totales->toArray(), $promedio);

            // Crear ranking de dependencias
            $ranking = $estadisticas
                ->sortByDesc('total_elementos')
                ->take($limit)
                ->map(function ($item, $index) use ($promedio) {
                    $puntuacion = $promedio > 0 ? round(($item->total_elementos / $promedio) * 100, 2) : 0;

                    return [
                        'posicion' => $index + 1,
                        'dependencia' => $item->dependencia->nom_organico ?? 'N/A',
                        'codigo' => $item->dependencia->cod_organico ?? 'N/A',
                        'total_elementos' => $item->total_elementos,
                        'series' => $item->series,
                        'subseries' => $item->subseries,
                        'tipos_documento' => $item->tipos_documento,
                        'puntuacion' => $puntuacion,
                        'porcentaje_del_promedio' => $promedio > 0 ? round(($item->total_elementos / $promedio) * 100, 2) : 0
                    ];
                });

            // Calcular distribución por tipo
            $distribucionPorTipo = [];
            if ($tipo) {
                $campo = match ($tipo) {
                    'Serie' => 'series',
                    'SubSerie' => 'subseries',
                    'TipoDocumento' => 'tipos_documento',
                    default => 'total_elementos'
                };

                $valores = $estadisticas->pluck($campo);
                $distribucionPorTipo[$tipo] = [
                    'promedio' => round($valores->avg(), 2),
                    'maxima' => $valores->max(),
                    'minima' => $valores->min(),
                    'total' => $valores->sum()
                ];
            } else {
                $distribucionPorTipo = [
                    'Serie' => [
                        'promedio' => round($estadisticas->avg('series'), 2),
                        'maxima' => $estadisticas->max('series'),
                        'minima' => $estadisticas->min('series'),
                        'total' => $estadisticas->sum('series')
                    ],
                    'SubSerie' => [
                        'promedio' => round($estadisticas->avg('subseries'), 2),
                        'maxima' => $estadisticas->max('subseries'),
                        'minima' => $estadisticas->min('subseries'),
                        'total' => $estadisticas->sum('subseries')
                    ],
                    'TipoDocumento' => [
                        'promedio' => round($estadisticas->avg('tipos_documento'), 2),
                        'maxima' => $estadisticas->max('tipos_documento'),
                        'minima' => $estadisticas->min('tipos_documento'),
                        'total' => $estadisticas->sum('tipos_documento')
                    ]
                ];
            }

            // Análisis de rendimiento
            $analisisRendimiento = [
                'dependencias_sobre_promedio' => $estadisticas->where('total_elementos', '>', $promedio)->count(),
                'dependencias_bajo_promedio' => $estadisticas->where('total_elementos', '<', $promedio)->count(),
                'dependencias_en_promedio' => $estadisticas->where('total_elementos', $promedio)->count(),
                'coeficiente_variacion' => $promedio > 0 ? round(($desviacion / $promedio) * 100, 2) : 0
            ];

            $data = [
                'ranking_dependencias' => $ranking,
                'metricas_generales' => [
                    'promedio_elementos' => round($promedio, 2),
                    'mediana_elementos' => round($mediana, 2),
                    'desviacion_estandar' => round($desviacion, 2),
                    'total_dependencias_analizadas' => $estadisticas->count()
                ],
                'distribucion_por_tipo' => $distribucionPorTipo,
                'analisis_rendimiento' => $analisisRendimiento,
                'filtros_aplicados' => [
                    'tipo' => $tipo ?? 'Todos',
                    'limit' => $limit
                ]
            ];

            return $this->successResponse($data, 'Estadísticas comparativas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas comparativas', $e->getMessage(), 500);
        }
    }

    /**
     * Valida la jerarquía de elementos TRD según su tipo.
     *
     * @param array $data Datos del elemento
     * @return bool
     */
    private function validarJerarquia(array $data): bool
    {
        if (in_array($data['tipo'], ['SubSerie', 'TipoDocumento'])) {
            if (!isset($data['parent']) || empty($data['parent'])) {
                return false;
            }

            $parent = ClasificacionDocumentalTRD::find($data['parent']);
            if (!$parent) {
                return false;
            }

            if ($data['tipo'] === 'SubSerie' && $parent->tipo !== 'Serie') {
                return false;
            }

            if ($data['tipo'] === 'TipoDocumento' && !in_array($parent->tipo, ['Serie', 'SubSerie'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Procesa el archivo Excel subido.
     *
     * @param ImportarTRDRequest $request
     * @return string
     */
    private function procesarArchivoExcel(ImportarTRDRequest $request): string
    {
        $filePath = $request->file('file')->storeAs(
            'temp_files',
            'TRD_import_' . now()->timestamp . '.xlsx'
        );

        return storage_path('app/' . $filePath);
    }

    /**
     * Obtiene la dependencia desde el archivo Excel.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @return CalidadOrganigrama|null
     */
    private function obtenerDependencia($sheet): ?CalidadOrganigrama
    {
        $codDependencia = $sheet->getCell('B4')->getValue();
        return CalidadOrganigrama::where('cod_organico', $codDependencia)->first();
    }

    /**
     * Verifica si la dependencia tiene una versión pendiente.
     *
     * @param int $dependenciaId
     * @return bool
     */
    private function tieneVersionPendiente(int $dependenciaId): bool
    {
        return ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->where('estado_version', 'TEMP')
            ->exists();
    }

    /**
     * Crea una nueva versión para la dependencia.
     *
     * @param int $dependenciaId
     * @return ClasificacionDocumentalTRDVersion
     */
    private function crearNuevaVersion(int $dependenciaId): ClasificacionDocumentalTRDVersion
    {
        $ultimaVersion = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->max('version');

        return ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $dependenciaId,
            'version' => ($ultimaVersion ?? 0) + 1,
            'estado_version' => 'TEMP',
            'user_register' => auth()->id(),
        ]);
    }

    /**
     * Procesa los datos TRD desde el archivo Excel.
     *
     * @param array $data
     * @param int $dependenciaId
     * @param int $versionId
     */
    private function procesarDatosTRD(array $data, int $dependenciaId, int $versionId): void
    {
        $idSerie = null;
        $idSubSerie = null;

        foreach ($data as $index => $row) {
            if ($index < 6) continue; // Saltar filas de encabezado

            [$codDep, $codSerie, $codSubSerie, $nom, $a_g, $a_c, $ct, $e, $m_d, $s, $procedimiento] = $row;

            // Convertir valores booleanos
            $ct = $ct ? 1 : 0;
            $e = $e ? 1 : 0;
            $m_d = $m_d ? 1 : 0;
            $s = $s ? 1 : 0;

            if ($codSerie) {
                $serieModel = ClasificacionDocumentalTRD::create([
                    'tipo' => 'Serie',
                    'cod' => $codSerie,
                    'nom' => $nom,
                    'a_g' => $a_g,
                    'a_c' => $a_c,
                    'ct' => $ct,
                    'e' => $e,
                    'm_d' => $m_d,
                    's' => $s,
                    'procedimiento' => $procedimiento,
                    'dependencia_id' => $dependenciaId,
                    'version_id' => $versionId,
                    'user_register' => auth()->id(),
                ]);
                $idSerie = $serieModel->id;
            }

            if ($codSubSerie) {
                $subSerieModel = ClasificacionDocumentalTRD::create([
                    'tipo' => 'SubSerie',
                    'cod' => $codSubSerie,
                    'nom' => $nom,
                    'parent' => $idSerie,
                    'dependencia_id' => $dependenciaId,
                    'version_id' => $versionId,
                    'user_register' => auth()->id(),
                ]);
                $idSubSerie = $subSerieModel->id;
            }

            if ($nom && !$codSerie && !$codSubSerie) {
                ClasificacionDocumentalTRD::create([
                    'tipo' => 'TipoDocumento',
                    'nom' => $nom,
                    'parent' => $idSubSerie ?? $idSerie,
                    'dependencia_id' => $dependenciaId,
                    'version_id' => $versionId,
                    'user_register' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * Limpia el archivo temporal después del procesamiento.
     *
     * @param string $filePath
     */
    private function limpiarArchivoTemporal(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Calcula la mediana de un array de números.
     *
     * @param array $numeros
     * @return float
     */
    private function calcularMediana(array $numeros): float
    {
        sort($numeros);
        $count = count($numeros);

        if ($count === 0) {
            return 0;
        }

        if ($count % 2 === 0) {
            $medio1 = $numeros[($count / 2) - 1];
            $medio2 = $numeros[$count / 2];
            return ($medio1 + $medio2) / 2;
        } else {
            return $numeros[floor($count / 2)];
        }
    }

    /**
     * Calcula la desviación estándar de un array de números.
     *
     * @param array $numeros
     * @param float $promedio
     * @return float
     */
    private function calcularDesviacionEstandar(array $numeros, float $promedio): float
    {
        if (empty($numeros)) {
            return 0;
        }

        $sumaCuadrados = 0;
        foreach ($numeros as $numero) {
            $sumaCuadrados += pow($numero - $promedio, 2);
        }

        return sqrt($sumaCuadrados / count($numeros));
    }
}
