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

/**
 * Controlador para la gestión de la Tabla de Retención Documental (TRD).
 *
 * Este controlador maneja todas las operaciones relacionadas con la clasificación documental,
 * incluyendo la creación, actualización, eliminación y consulta de elementos TRD (Series,
 * SubSeries y Tipos de Documento), así como la importación masiva desde archivos Excel
 * y la generación de estadísticas avanzadas.
 *
 * @package App\Http\Controllers\ClasificacionDocumental
 * @author Sistema OCobo
 * @version 2.0
 * @since 2025-01-01
 */
class ClasificacionDocumentalTRDController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene el listado de TRD (Tabla de Retención Documental) organizadas jerárquicamente.
     *
     * Este método retorna todas las series y subseries de TRD que no tienen padre,
     * organizadas en una estructura jerárquica con sus elementos hijos. Permite filtrar
     * por dependencia y tipo de elemento.
     *
     * @param Request $request La solicitud HTTP con parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la estructura TRD jerárquica
     *
     * @queryParam dependencia_id integer ID de la dependencia para filtrar. Example: 1
     * @queryParam tipo string Tipo de elemento para filtrar (Serie, SubSerie). Example: "Serie"
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
     *       "dependencia_id": 1,
     *       "parent": null,
     *       "a_g": "5",
     *       "a_c": "10",
     *       "ct": true,
     *       "e": false,
     *       "m_d": false,
     *       "s": false,
     *       "procedimiento": "PROC-001",
     *       "estado": true,
     *       "children": [
     *         {
     *           "id": 2,
     *           "tipo": "SubSerie",
     *           "cod": "SS001",
     *           "nom": "Contratos de Personal",
     *           "parent": 1,
     *           "children": []
     *         }
     *       ],
     *       "dependencia": {
     *         "id": 1,
     *         "nom_organico": "JUNTA DIRECTIVA",
     *         "cod_organico": "JD001"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las TRD",
     *   "error": "Mensaje de error específico"
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
     * Este método permite crear series, subseries o tipos de documento con validaciones
     * específicas según el tipo de elemento. Las validaciones incluyen verificación de
     * jerarquía, códigos únicos y dependencias.
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
     *     "nom": "Gestión Administrativa",
     *     "dependencia_id": 1,
     *     "parent": null,
     *     "a_g": "5",
     *     "a_c": "10",
     *     "ct": true,
     *     "e": false,
     *     "m_d": false,
     *     "s": false,
     *     "procedimiento": "PROC-001",
     *     "estado": true,
     *     "created_at": "2025-07-30T11:35:27.000000Z",
     *     "updated_at": "2025-07-30T11:35:27.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "cod": ["El código ya existe para esta dependencia"],
     *     "parent": ["El elemento padre no existe"]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el elemento TRD",
     *   "error": "Mensaje de error específico"
     * }
     */
    public function store(StoreClasificacionDocumentalRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['user_register'] = auth()->id();

            $trd = ClasificacionDocumentalTRD::create($data);

            DB::commit();

            return $this->successResponse($trd, 'Elemento TRD creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el elemento TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un elemento TRD específico por su ID.
     *
     * Este método retorna la información completa de un elemento TRD específico,
     * incluyendo sus relaciones con dependencias y elementos hijos.
     *
     * @param int $id ID del elemento TRD
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el elemento TRD
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elemento TRD obtenido exitosamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Serie",
     *     "cod": "S001",
     *     "nom": "Gestión Administrativa",
     *     "dependencia_id": 1,
     *     "parent": null,
     *     "a_g": "5",
     *     "a_c": "10",
     *     "ct": true,
     *     "e": false,
     *     "m_d": false,
     *     "s": false,
     *     "procedimiento": "PROC-001",
     *     "estado": true,
     *     "children": [...],
     *     "dependencia": {
     *       "id": 1,
     *       "nom_organico": "JUNTA DIRECTIVA",
     *       "cod_organico": "JD001"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Elemento TRD no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el elemento TRD",
     *   "error": "Mensaje de error específico"
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
     * Este método permite actualizar la información de un elemento TRD existente,
     * con validaciones específicas para mantener la integridad de la jerarquía.
     *
     * @param UpdateClasificacionDocumentalRequest $request La solicitud HTTP validada
     * @param int $id ID del elemento TRD a actualizar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el elemento actualizado
     *
     * @bodyParam tipo string Tipo de elemento (Serie, SubSerie, TipoDocumento). Example: "Serie"
     * @bodyParam cod string Código único del elemento. Example: "S001"
     * @bodyParam nom string Nombre del elemento. Example: "Gestión Administrativa"
     * @bodyParam parent integer ID del elemento padre. Example: 1
     * @bodyParam dependencia_id integer ID de la dependencia. Example: 1
     * @bodyParam a_g string Años de gestión. Example: "5"
     * @bodyParam a_c string Años de centralización. Example: "10"
     * @bodyParam ct boolean Conservación total. Example: true
     * @bodyParam e boolean Eliminación. Example: false
     * @bodyParam m_d boolean Microfilmación digital. Example: false
     * @bodyParam s boolean Selección. Example: false
     * @bodyParam procedimiento string Procedimiento asociado. Example: "PROC-001"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elemento TRD actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Serie",
     *     "cod": "S001",
     *     "nom": "Gestión Administrativa Actualizada",
     *     "dependencia_id": 1,
     *     "parent": null,
     *     "a_g": "5",
     *     "a_c": "10",
     *     "ct": true,
     *     "e": false,
     *     "m_d": false,
     *     "s": false,
     *     "procedimiento": "PROC-001",
     *     "estado": true,
     *     "updated_at": "2025-07-30T11:35:27.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Elemento TRD no encontrado"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "cod": ["El código ya existe para esta dependencia"],
     *     "parent": ["No puede cambiar el tipo si tiene elementos hijos"]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el elemento TRD",
     *   "error": "Mensaje de error específico"
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

            $data = $request->validated();
            $trd->update($data);

            DB::commit();

            return $this->successResponse($trd, 'Elemento TRD actualizado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el elemento TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un elemento TRD del sistema.
     *
     * Este método elimina un elemento TRD específico, con validaciones para asegurar
     * que no se eliminen elementos que tienen hijos o están en uso.
     *
     * @param int $id ID del elemento TRD a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON de confirmación
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elemento TRD eliminado exitosamente",
     *   "data": null
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Elemento TRD no encontrado"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "No se puede eliminar el elemento TRD",
     *   "error": "El elemento tiene elementos hijos asociados"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el elemento TRD",
     *   "error": "Mensaje de error específico"
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

            // Verificar si tiene elementos hijos
            if ($trd->children()->count() > 0) {
                return $this->errorResponse(
                    'No se puede eliminar el elemento TRD',
                    'El elemento tiene elementos hijos asociados',
                    422
                );
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
     * Importa elementos TRD desde un archivo Excel.
     *
     * Este método permite importar masivamente elementos TRD desde un archivo Excel,
     * procesando automáticamente la estructura jerárquica y creando versiones
     * temporales que requieren aprobación antes de ser activadas.
     *
     * @param ImportarTRDRequest $request La solicitud HTTP con el archivo Excel
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el resultado de la importación
     *
     * @bodyParam archivo file required Archivo Excel con datos TRD (.xlsx, .xls). Example: trd_data.xlsx
     * @bodyParam dependencia_id integer required ID de la dependencia para la importación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "TRD importada exitosamente",
     *   "data": {
     *     "elementos_importados": 25,
     *     "series_creadas": 5,
     *     "subseries_creadas": 15,
     *     "tipos_documento_creados": 5,
     *     "version_id": 1,
     *     "archivo_procesado": "trd_data.xlsx",
     *     "dependencia": {
     *       "id": 1,
     *       "nom_organico": "JUNTA DIRECTIVA",
     *       "cod_organico": "JD001"
     *     },
     *     "fecha_importacion": "2025-07-30T11:35:27.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "archivo": ["El archivo debe ser un archivo Excel válido"],
     *     "dependencia_id": ["La dependencia no existe"]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al importar TRD",
     *   "error": "Mensaje de error específico"
     * }
     */
    public function importarTRD(ImportarTRDRequest $request)
    {
        try {
            DB::beginTransaction();

            // Procesar archivo Excel
            $filePath = $this->procesarArchivoExcel($request);

            // Obtener dependencia
            $dependencia = $this->obtenerDependencia($request->dependencia_id);

            if (!$dependencia) {
                return $this->errorResponse('Dependencia no encontrada', null, 404);
            }

            // Verificar si ya existe una versión pendiente
            if ($this->tieneVersionPendiente($dependencia->id)) {
                return $this->errorResponse(
                    'Ya existe una versión pendiente de aprobación para esta dependencia',
                    'Debe aprobar o rechazar la versión actual antes de crear una nueva',
                    422
                );
            }

            // Crear nueva versión temporal
            $version = $this->crearNuevaVersion($dependencia->id);

            // Procesar datos del Excel
            $this->procesarDatosTRD($filePath, $dependencia->id, $version->id);

            // Limpiar archivo temporal
            $this->limpiarArchivoTemporal($filePath);

            $resultado = [
                'elementos_importados' => $version->trds()->count(),
                'series_creadas' => $version->trds()->where('tipo', 'Serie')->count(),
                'subseries_creadas' => $version->trds()->where('tipo', 'SubSerie')->count(),
                'tipos_documento_creados' => $version->trds()->where('tipo', 'TipoDocumento')->count(),
                'version_id' => $version->id,
                'archivo_procesado' => $request->file('archivo')->getClientOriginalName(),
                'dependencia' => $dependencia,
                'fecha_importacion' => now()
            ];

            DB::commit();

            return $this->successResponse($resultado, 'TRD importada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al importar TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas específicas de una dependencia.
     *
     * Este método retorna estadísticas detalladas de los elementos TRD
     * asociados a una dependencia específica.
     *
     * @param int $id ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas de la dependencia
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "dependencia": {
     *       "id": 1,
     *       "nom_organico": "JUNTA DIRECTIVA",
     *       "cod_organico": "JD001"
     *     },
     *     "total_elementos": 8,
     *     "series": 2,
     *     "subseries": 3,
     *     "tipos_documento": 3,
     *     "distribucion_por_tipo": {
     *       "Serie": 25.0,
     *       "SubSerie": 37.5,
     *       "TipoDocumento": 37.5
     *     },
     *     "ultima_actualizacion": "2025-07-30T11:35:27.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Dependencia no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener estadísticas",
     *   "error": "Mensaje de error específico"
     * }
     */
    public function estadistica($id)
    {
        try {
            $dependencia = CalidadOrganigrama::find($id);

            if (!$dependencia) {
                return $this->errorResponse('Dependencia no encontrada', null, 404);
            }

            $estadisticas = ClasificacionDocumentalTRD::where('dependencia_id', $id)
                ->selectRaw('
                    COUNT(*) as total_elementos,
                    SUM(CASE WHEN tipo = "Serie" THEN 1 ELSE 0 END) as series,
                    SUM(CASE WHEN tipo = "SubSerie" THEN 1 ELSE 0 END) as subseries,
                    SUM(CASE WHEN tipo = "TipoDocumento" THEN 1 ELSE 0 END) as tipos_documento
                ')
                ->first();

            $total = $estadisticas->total_elementos;
            $distribucion = [];

            if ($total > 0) {
                $distribucion = [
                    'Serie' => round(($estadisticas->series / $total) * 100, 2),
                    'SubSerie' => round(($estadisticas->subseries / $total) * 100, 2),
                    'TipoDocumento' => round(($estadisticas->tipos_documento / $total) * 100, 2)
                ];
            }

            $data = [
                'dependencia' => $dependencia,
                'total_elementos' => $total,
                'series' => $estadisticas->series,
                'subseries' => $estadisticas->subseries,
                'tipos_documento' => $estadisticas->tipos_documento,
                'distribucion_por_tipo' => $distribucion,
                'ultima_actualizacion' => now()
            ];

            return $this->successResponse($data, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Lista elementos TRD por dependencia específica.
     *
     * Este método retorna todos los elementos TRD asociados a una dependencia
     * específica, organizados en estructura jerárquica.
     *
     * @param int $id ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con elementos TRD de la dependencia
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Elementos TRD obtenidos exitosamente",
     *   "data": {
     *     "dependencia": {
     *       "id": 1,
     *       "nom_organico": "JUNTA DIRECTIVA",
     *       "cod_organico": "JD001"
     *     },
     *     "elementos": [
     *       {
     *         "id": 1,
     *         "tipo": "Serie",
     *         "cod": "S001",
     *         "nom": "Gestión Administrativa",
     *         "children": [...]
     *       }
     *     ],
     *     "total_elementos": 8
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Dependencia no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener elementos TRD",
     *   "error": "Mensaje de error específico"
     * }
     */
    public function listarPorDependencia($id)
    {
        try {
            $dependencia = CalidadOrganigrama::find($id);

            if (!$dependencia) {
                return $this->errorResponse('Dependencia no encontrada', null, 404);
            }

            $elementos = ClasificacionDocumentalTRD::where('dependencia_id', $id)
                ->whereNull('parent')
                ->with(['children', 'dependencia'])
                ->orderBy('cod', 'asc')
                ->get();

            $data = [
                'dependencia' => $dependencia,
                'elementos' => $elementos,
                'total_elementos' => ClasificacionDocumentalTRD::where('dependencia_id', $id)->count()
            ];

            return $this->successResponse($data, 'Elementos TRD obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener elementos TRD', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas totales del sistema TRD.
     *
     * Este método retorna estadísticas generales de todo el sistema TRD,
     * incluyendo totales, distribución por tipos, dependencias más activas
     * y métricas agregadas.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas totales
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas totales obtenidas exitosamente",
     *   "data": {
     *     "total_elementos": 8,
     *     "total_series": 2,
     *     "total_subseries": 3,
     *     "total_tipos_documento": 3,
     *     "total_dependencias": 1,
     *     "elementos_por_dependencia": {
     *       "promedio": 8.0,
     *       "maxima": 8,
     *       "minima": 8
     *     },
     *     "distribucion_por_tipo": {
     *       "Serie": 25.0,
     *       "SubSerie": 37.5,
     *       "TipoDocumento": 37.5
     *     },
     *     "dependencias_mas_activas": [
     *       {
     *         "dependencia": "JUNTA DIRECTIVA",
     *         "codigo": "JD001",
     *         "total_elementos": 8,
     *         "series": 2,
     *         "subseries": 3,
     *         "tipos_documento": 3
     *       }
     *     ],
     *     "fecha_actualizacion": "2025-07-30T11:35:27.000000Z"
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener estadísticas totales",
     *   "error": "Mensaje de error específico"
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
     * que tiene elementos TRD registrados, incluyendo porcentajes por tipo
     * y resumen general del sistema.
     *
     * @param Request $request La solicitud HTTP con parámetros de paginación y ordenamiento
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
     *     "estadisticas": {
     *       "current_page": 1,
     *       "data": [
     *         {
     *           "dependencia": {
     *             "id": 1,
     *             "nom_organico": "JUNTA DIRECTIVA",
     *             "cod_organico": "JD001"
     *           },
     *           "total_elementos": 8,
     *           "series": 2,
     *           "subseries": 3,
     *           "tipos_documento": 3,
     *           "porcentaje_series": 25.0,
     *           "porcentaje_subseries": 37.5,
     *           "porcentaje_tipos_documento": 37.5
     *         }
     *       ],
     *       "total": 1,
     *       "per_page": 15
     *     },
     *     "resumen": {
     *       "total_dependencias": 1,
     *       "promedio_elementos": 8.0,
     *       "dependencia_mas_activa": "JUNTA DIRECTIVA",
     *       "dependencia_menos_activa": "JUNTA DIRECTIVA",
     *       "total_elementos_sistema": 8
     *     }
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener estadísticas por dependencias",
     *   "error": "Mensaje de error específico"
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
     * incluyendo rankings, promedios, métricas de rendimiento y análisis
     * estadístico avanzado con medianas y desviaciones estándar.
     *
     * @param Request $request La solicitud HTTP con parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas comparativas
     *
     * @queryParam limit integer Número de dependencias a incluir en el ranking (por defecto: 10). Example: 5
     * @queryParam tipo string Filtrar por tipo específico (Serie, SubSerie, TipoDocumento). Example: "Serie"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas comparativas obtenidas exitosamente",
     *   "data": {
     *     "ranking_dependencias": [
     *       {
     *         "posicion": 1,
     *         "dependencia": "JUNTA DIRECTIVA",
     *         "codigo": "JD001",
     *         "total_elementos": 8,
     *         "series": 2,
     *         "subseries": 3,
     *         "tipos_documento": 3,
     *         "puntuacion": 100.0,
     *         "porcentaje_del_promedio": 100.0
     *       }
     *     ],
     *     "metricas_generales": {
     *       "promedio_elementos": 8.0,
     *       "mediana_elementos": 8.0,
     *       "desviacion_estandar": 0.0,
     *       "total_dependencias_analizadas": 1
     *     },
     *     "distribucion_por_tipo": {
     *       "Serie": {
     *         "promedio": 2.0,
     *         "maxima": 2,
     *         "minima": 2,
     *         "total": 2
     *       },
     *       "SubSerie": {
     *         "promedio": 3.0,
     *         "maxima": 3,
     *         "minima": 3,
     *         "total": 3
     *       },
     *       "TipoDocumento": {
     *         "promedio": 3.0,
     *         "maxima": 3,
     *         "minima": 3,
     *         "total": 3
     *       }
     *     },
     *     "analisis_rendimiento": {
     *       "dependencias_sobre_promedio": 1,
     *       "dependencias_bajo_promedio": 0,
     *       "dependencias_en_promedio": 0,
     *       "coeficiente_variacion": 0.0
     *     },
     *     "filtros_aplicados": {
     *       "tipo": "Todos",
     *       "limit": 10
     *     }
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener estadísticas comparativas",
     *   "error": "Mensaje de error específico"
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
     * Este método verifica que la jerarquía de elementos TRD sea válida
     * según las reglas de negocio establecidas.
     *
     * @param array $data Datos del elemento TRD a validar
     * @return bool True si la jerarquía es válida, false en caso contrario
     */
    private function validarJerarquia(array $data): bool
    {
        $tipo = $data['tipo'] ?? '';
        $parent = $data['parent'] ?? null;

        // Series no pueden tener padre
        if ($tipo === 'Serie' && $parent !== null) {
            return false;
        }

        // SubSeries deben tener padre y debe ser una Serie
        if ($tipo === 'SubSerie') {
            if ($parent === null) {
                return false;
            }
            $parentElement = ClasificacionDocumentalTRD::find($parent);
            if (!$parentElement || $parentElement->tipo !== 'Serie') {
                return false;
            }
        }

        // Tipos de Documento deben tener padre y debe ser una SubSerie
        if ($tipo === 'TipoDocumento') {
            if ($parent === null) {
                return false;
            }
            $parentElement = ClasificacionDocumentalTRD::find($parent);
            if (!$parentElement || $parentElement->tipo !== 'SubSerie') {
                return false;
            }
        }

        return true;
    }

    /**
     * Procesa el archivo Excel subido y retorna la ruta del archivo temporal.
     *
     * Este método valida el archivo Excel, lo guarda temporalmente y
     * retorna la ruta para su procesamiento posterior.
     *
     * @param ImportarTRDRequest $request La solicitud HTTP con el archivo
     * @return string Ruta del archivo temporal procesado
     * @throws \Exception Si hay error al procesar el archivo
     */
    private function procesarArchivoExcel(ImportarTRDRequest $request): string
    {
        $file = $request->file('archivo');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = storage_path('app/temp/' . $fileName);

        // Crear directorio temporal si no existe
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file->move(dirname($filePath), $fileName);

        return $filePath;
    }

    /**
     * Obtiene la dependencia desde el ID proporcionado.
     *
     * Este método busca la dependencia en el sistema de organigrama
     * y retorna la información completa de la dependencia.
     *
     * @param int $dependenciaId ID de la dependencia a buscar
     * @return CalidadOrganigrama|null La dependencia encontrada o null si no existe
     */
    private function obtenerDependencia(int $dependenciaId): ?CalidadOrganigrama
    {
        return CalidadOrganigrama::find($dependenciaId);
    }

    /**
     * Verifica si una dependencia tiene una versión pendiente de aprobación.
     *
     * Este método consulta si existe una versión TRD con estado TEMP
     * para la dependencia especificada.
     *
     * @param int $dependenciaId ID de la dependencia a verificar
     * @return bool True si existe una versión pendiente, false en caso contrario
     */
    private function tieneVersionPendiente(int $dependenciaId): bool
    {
        return ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->where('estado', 'TEMP')
            ->exists();
    }

    /**
     * Crea una nueva versión temporal de TRD para una dependencia.
     *
     * Este método crea una nueva versión con estado TEMP que requiere
     * aprobación antes de ser activada en el sistema.
     *
     * @param int $dependenciaId ID de la dependencia para la nueva versión
     * @return ClasificacionDocumentalTRDVersion La versión creada
     */
    private function crearNuevaVersion(int $dependenciaId): ClasificacionDocumentalTRDVersion
    {
        return ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $dependenciaId,
            'estado' => 'TEMP',
            'fecha_creacion' => now(),
            'user_register' => auth()->id()
        ]);
    }

    /**
     * Procesa los datos TRD desde el archivo Excel.
     *
     * Este método lee el archivo Excel, procesa cada fila y crea los
     * elementos TRD correspondientes asociados a la versión especificada.
     *
     * @param string $filePath Ruta del archivo Excel a procesar
     * @param int $dependenciaId ID de la dependencia
     * @param int $versionId ID de la versión TRD
     * @throws \Exception Si hay error al procesar los datos
     */
    private function procesarDatosTRD(string $filePath, int $dependenciaId, int $versionId): void
    {
        $idSerie = null;
        $idSubSerie = null;

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        foreach ($data as $index => $row) {
            // Saltar filas de encabezado
            if ($index < 6) {
                continue;
            }

            // Verificar que la fila tenga datos
            if (empty(array_filter($row))) {
                continue;
            }

            $tipo = trim($row[0] ?? '');
            $codigo = trim($row[1] ?? '');
            $nombre = trim($row[2] ?? '');
            $ag = trim($row[3] ?? '');
            $ac = trim($row[4] ?? '');
            $ct = strtolower(trim($row[5] ?? '')) === 'si';
            $e = strtolower(trim($row[6] ?? '')) === 'si';
            $md = strtolower(trim($row[7] ?? '')) === 'si';
            $s = strtolower(trim($row[8] ?? '')) === 'si';
            $procedimiento = trim($row[9] ?? '');

            // Validar datos mínimos
            if (empty($tipo) || empty($codigo) || empty($nombre)) {
                continue;
            }

            // Determinar parent según el tipo
            $parent = null;
            switch ($tipo) {
                case 'Serie':
                    $idSerie = null;
                    $idSubSerie = null;
                    break;
                case 'SubSerie':
                    if ($idSerie === null) {
                        continue; // SubSerie sin Serie padre
                    }
                    $parent = $idSerie;
                    $idSubSerie = null;
                    break;
                case 'TipoDocumento':
                    if ($idSubSerie === null) {
                        continue; // TipoDocumento sin SubSerie padre
                    }
                    $parent = $idSubSerie;
                    break;
                default:
                    continue; // Tipo no válido
            }

            // Crear elemento TRD
            $elemento = ClasificacionDocumentalTRD::create([
                'tipo' => $tipo,
                'cod' => $codigo,
                'nom' => $nombre,
                'parent' => $parent,
                'dependencia_id' => $dependenciaId,
                'a_g' => $ag,
                'a_c' => $ac,
                'ct' => $ct,
                'e' => $e,
                'm_d' => $md,
                's' => $s,
                'procedimiento' => $procedimiento,
                'estado' => true,
                'user_register' => auth()->id(),
                'version_trd_id' => $versionId
            ]);

            // Actualizar referencias para jerarquía
            if ($tipo === 'Serie') {
                $idSerie = $elemento->id;
            } elseif ($tipo === 'SubSerie') {
                $idSubSerie = $elemento->id;
            }
        }
    }

    /**
     * Limpia el archivo temporal después del procesamiento.
     *
     * Este método elimina el archivo Excel temporal que se utilizó
     * para la importación de datos TRD.
     *
     * @param string $filePath Ruta del archivo temporal a eliminar
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
     * Este método calcula la mediana (valor central) de un conjunto
     * de números, ordenándolos primero y luego tomando el valor central.
     *
     * @param array $numeros Array de números para calcular la mediana
     * @return float La mediana calculada
     */
    private function calcularMediana(array $numeros): float
    {
        if (empty($numeros)) {
            return 0.0;
        }

        sort($numeros);
        $count = count($numeros);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            // Número par de elementos, promedio de los dos centrales
            return ($numeros[$middle - 1] + $numeros[$middle]) / 2;
        } else {
            // Número impar de elementos, valor central
            return $numeros[$middle];
        }
    }

    /**
     * Calcula la desviación estándar de un array de números.
     *
     * Este método calcula la desviación estándar de un conjunto de números,
     * que es una medida de la dispersión de los datos respecto a la media.
     *
     * @param array $numeros Array de números para calcular la desviación estándar
     * @param float $promedio El promedio de los números (calculado previamente)
     * @return float La desviación estándar calculada
     */
    private function calcularDesviacionEstandar(array $numeros, float $promedio): float
    {
        if (empty($numeros)) {
            return 0.0;
        }

        $sumaCuadrados = 0;
        foreach ($numeros as $numero) {
            $sumaCuadrados += pow($numero - $promedio, 2);
        }

        $varianza = $sumaCuadrados / count($numeros);
        return sqrt($varianza);
    }
}
