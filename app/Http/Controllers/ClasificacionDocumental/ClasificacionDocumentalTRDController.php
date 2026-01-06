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
use App\Helpers\ArchivoHelper;
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
     * extrayendo automáticamente el código de dependencia desde la celda B4,
     * procesando la estructura jerárquica y creando versiones temporales
     * que requieren aprobación antes de ser activadas.
     *
     * @param ImportarTRDRequest $request La solicitud HTTP con el archivo Excel
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el resultado de la importación
     *
     * @bodyParam archivo file required Archivo Excel con datos TRD (.xlsx, .xls) con código de dependencia en celda B4. Example: trd_data.xlsx
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
     *     "codigo_dependencia_extraido": "GRE",
     *     "dependencia": {
     *       "id": 3,
     *       "nom_organico": "GERENCIA",
     *       "cod_organico": "GRE"
     *     },
     *     "fecha_importacion": "2025-07-30T11:35:27.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "No se encontró el código de dependencia en la celda B4 del archivo Excel"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No se encontró una dependencia con el código: XYZ"
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

            // Leer el archivo Excel para extraer el código de dependencia de la celda B4
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Extraer código de dependencia desde la celda B4
            $codigoDependencia = trim($worksheet->getCell('B4')->getValue() ?? '');

            if (empty($codigoDependencia)) {
                $this->limpiarArchivoTemporal($filePath);
                return $this->errorResponse('No se encontró el código de dependencia en la celda B4 del archivo Excel', null, 422);
            }

            // Buscar dependencia por código orgánico
            $dependencia = CalidadOrganigrama::where('cod_organico', $codigoDependencia)
                ->where('tipo', 'Dependencia')
                ->first();

            if (!$dependencia) {
                $this->limpiarArchivoTemporal($filePath);
                return $this->errorResponse("No se encontró una dependencia con el código: {$codigoDependencia}", null, 404);
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
                'archivo_procesado' => $request->hasFile('archivo') ? $request->file('archivo')->getClientOriginalName() : 'archivo_importado.xlsx',
                'codigo_dependencia_extraido' => $codigoDependencia,
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
     * Descarga la plantilla Excel para importar TRD.
     *
     * Este método permite descargar la plantilla Excel que se utiliza
     * para importar datos de TRD al sistema.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse Respuesta de descarga del archivo o error
     *
     * @response 200 {
     *   "file": "binary content"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Plantilla no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al descargar la plantilla",
     *   "error": "Error message"
     * }
     */
    public function descargarPlantilla()
    {
        try {
            $rutaArchivo = 'planitllas/Ocobo - Plantilla TRD.xlsx';

            // Verificar que el archivo existe
            if (!Storage::exists($rutaArchivo)) {
                return $this->errorResponse('Plantilla no encontrada', null, 404);
            }

            // Descargar el archivo
            return Storage::download($rutaArchivo, 'Ocobo - Plantilla TRD.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar la plantilla', $e->getMessage(), 500);
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
                /* 'elementos_por_dependencia' => [
                    'promedio' => round($promedio, 2),
                    'maxima' => $maxima,
                    'minima' => $minima
                ],
                'distribucion_por_tipo' => $distribucionPorTipo,
                'dependencias_mas_activas' => $dependenciasMasActivas,
                'fecha_actualizacion' => now()->format('Y-m-d H:i:s') */
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
        $rutaArchivo = ArchivoHelper::guardarArchivo($request, 'archivo', 'temp_files');

        if (!$rutaArchivo) {
            throw new \Exception('No se ha proporcionado ningún archivo válido');
        }

        $diskConfig = config("filesystems.disks.temp_files");
        $root = $diskConfig['root'] ?? storage_path('app/temp_files');

        return $root . '/' . $rutaArchivo;
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
            ->where('estado_version', 'TEMP')
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
        // Obtener la última versión para calcular el número de la nueva
        $ultimaVersion = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->max('version');

        return ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $dependenciaId,
            'version' => ($ultimaVersion ?? 0) + 1,
            'estado_version' => 'TEMP',
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
        $elementosInsertados = 0;
        $errores = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            foreach ($data as $index => $row) {
                // Saltar filas de encabezado
                if ($index < 6) {
                    continue;
                }

                // Obtener valores de las columnas
                $columnaA = trim($row[0] ?? '');
                $columnaB = trim($row[1] ?? '');
                $columnaC = trim($row[2] ?? '');
                $columnaD = trim($row[3] ?? ''); // Nombre
                $columnaE = trim($row[4] ?? ''); // Archivo Gestión
                $columnaF = trim($row[5] ?? ''); // Archivo Central
                $columnaG = trim($row[6] ?? ''); // CT
                $columnaH = trim($row[7] ?? ''); // E
                $columnaI = trim($row[8] ?? ''); // M/D
                $columnaJ = trim($row[9] ?? ''); // S
                $columnaK = trim($row[10] ?? ''); // Procedimiento

                // Determinar tipo según las reglas:
                // 1. Serie: A y B tienen información, C está vacía
                // 2. SubSerie: A, B y C tienen información
                // 3. TipoDocumento: A, B y C están todas vacías

                $tieneA = !empty($columnaA);
                $tieneB = !empty($columnaB);
                $tieneC = !empty($columnaC);

                $tipo = null;
                $codigo = null;
                $parent = null;
                $saltarFila = false;

                // Validar que tenga nombre (columna D)
                if (empty($columnaD)) {
                    continue;
                }

                // Determinar tipo
                if ($tieneA && $tieneB && !$tieneC) {
                    // Serie: A y B tienen información, C está vacía
                    $tipo = 'Serie';
                    $codigo = $columnaB; // Código es columna B
                    $parent = null; // Serie no tiene parent

                    // Resetear jerarquía cuando encontramos una nueva Serie
                    $idSerie = null;
                    $idSubSerie = null;
                } elseif ($tieneA && $tieneB && $tieneC) {
                    // SubSerie: A, B y C tienen información
                    $tipo = 'SubSerie';
                    $codigo = $columnaC; // Código es solo columna C

                    // Parent debe ser la Serie más reciente
                    if ($idSerie === null) {
                        $errores[] = "Fila " . ($index + 1) . ": SubSerie '{$codigo}' sin Serie padre";
                        $saltarFila = true;
                    } else {
                        $parent = $idSerie;
                        // Resetear SubSerie cuando encontramos una nueva
                        $idSubSerie = null;
                    }
                } elseif (!$tieneA && !$tieneB && !$tieneC) {
                    // TipoDocumento: A, B y C están todas vacías
                    $tipo = 'TipoDocumento';
                    $codigo = null; // TipoDocumento no tiene código

                    // Parent puede ser Serie o SubSerie más reciente
                    if ($idSubSerie !== null) {
                        $parent = $idSubSerie;
                    } elseif ($idSerie !== null) {
                        $parent = $idSerie;
                    } else {
                        $errores[] = "Fila " . ($index + 1) . ": TipoDocumento sin Serie o SubSerie padre";
                        $saltarFila = true;
                    }
                } else {
                    // Caso no válido: combinación no reconocida
                    continue;
                }

                // Saltar esta fila si hay error de jerarquía
                if ($saltarFila) {
                    continue;
                }

                try {
                    // Preparar datos según el tipo
                    $agLimpio = null;
                    $acLimpio = null;
                    $ct = false;
                    $e = false;
                    $md = false;
                    $s = false;
                    $procedimiento = null;

                    // Solo para SubSeries: procesar columnas E, F, G, H, I, J, K
                    if ($tipo === 'SubSerie') {
                        $agLimpio = !empty($columnaE) ? mb_substr(trim($columnaE), 0, 5) : null;
                        $acLimpio = !empty($columnaF) ? mb_substr(trim($columnaF), 0, 5) : null;
                        $ct = strtolower(trim($columnaG)) === 'si' || strtolower(trim($columnaG)) === 'x';
                        $e = strtolower(trim($columnaH)) === 'si' || strtolower(trim($columnaH)) === 'x';
                        $md = strtolower(trim($columnaI)) === 'si' || strtolower(trim($columnaI)) === 'x';
                        $s = strtolower(trim($columnaJ)) === 'si' || strtolower(trim($columnaJ)) === 'x';
                        $procedimiento = !empty($columnaK) ? $columnaK : null;
                    }

                    // Crear elemento TRD
                    $elemento = ClasificacionDocumentalTRD::create([
                        'tipo' => $tipo,
                        'cod' => $codigo,
                        'nom' => $columnaD,
                        'parent' => $parent,
                        'dependencia_id' => $dependenciaId,
                        'a_g' => $agLimpio,
                        'a_c' => $acLimpio,
                        'ct' => $ct,
                        'e' => $e,
                        'm_d' => $md,
                        's' => $s,
                        'procedimiento' => $procedimiento,
                        'estado' => true,
                        'user_register' => auth()->id(),
                        'version_id' => $versionId
                    ]);

                    $elementosInsertados++;

                    // Actualizar referencias para jerarquía DESPUÉS de crear el elemento
                    if ($tipo === 'Serie') {
                        $idSerie = $elemento->id;
                        $idSubSerie = null; // Resetear SubSerie cuando hay nueva Serie
                    } elseif ($tipo === 'SubSerie') {
                        $idSubSerie = $elemento->id;
                    }
                    // TipoDocumento no actualiza referencias, mantiene la Serie/SubSerie actual
                } catch (\Exception $e) {
                    $codigoDisplay = $codigo ?? 'sin código';
                    $errores[] = "Fila " . ($index + 1) . ": Error al insertar {$tipo} '{$codigoDisplay}' - {$e->getMessage()}";
                }
            }

            // Validar que se insertó al menos un elemento
            if ($elementosInsertados === 0) {
                $mensajeError = "No se insertó ningún elemento TRD. ";
                if (!empty($errores)) {
                    $mensajeError .= "Errores encontrados: " . implode('; ', array_slice($errores, 0, 10));
                } else {
                    $mensajeError .= "Verifique que el archivo tenga datos válidos en las columnas correctas. Total de filas procesadas: " . count($data);
                }
                throw new \Exception($mensajeError);
            }

            // Si hay errores pero se insertaron elementos, lanzar advertencia
            if (!empty($errores) && count($errores) > $elementosInsertados) {
                throw new \Exception("Se encontraron más errores que elementos insertados. Errores: " . implode('; ', array_slice($errores, 0, 10)));
            }
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new \Exception("Error al leer el archivo Excel: " . $e->getMessage());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Obtiene las clasificaciones documentales por dependencia en estructura jerárquica.
     *
     * Este método retorna todas las clasificaciones documentales (Series, SubSeries y Tipos de Documento)
     * que pertenecen a una dependencia específica, organizadas en una estructura de árbol jerárquico.
     * Incluye información de la dependencia y formatea los nombres con el tipo entre corchetes.
     *
     * @param int $dependencia_id ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la estructura jerárquica
     *
     * @urlParam dependencia_id integer required ID de la dependencia. Example: 3
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Clasificaciones por dependencia obtenidas exitosamente",
     *   "data": {
     *     "dependencia": {
     *       "id": 3,
     *       "nom_organico": "GERENCIA",
     *       "cod_organico": "100",
     *       "tipo": "Dependencia"
     *     },
     *     "clasificaciones": [
     *       {
     *         "id": 1,
     *         "cod": "1",
     *         "nom": "[SERIE] Correspondencia",
     *         "a_g": "5",
     *         "a_c": "15",
     *         "children": [
     *           {
     *             "id": 2,
     *             "cod": "1.1",
     *             "nom": "[SUBSERIE] Cartas",
     *             "children": [...]
     *           }
     *         ]
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Dependencia no encontrada",
     *   "data": null
     * }
     */
    public function clasificacionesPorDependencia(int $dependencia_id)
    {
        try {
            // Verificar que la dependencia existe
            $dependencia = CalidadOrganigrama::find($dependencia_id);

            if (!$dependencia) {
                return $this->errorResponse('Dependencia no encontrada', 404);
            }

            // Obtener todas las clasificaciones de la dependencia
            $clasificaciones = ClasificacionDocumentalTRD::where('dependencia_id', $dependencia_id)
                ->where('estado', true)
                ->orderBy('cod')
                ->get();

            // Debug temporal: Información sobre la consulta
            $debugInfo = [
                'dependencia_id_buscado' => $dependencia_id,
                'total_clasificaciones_encontradas' => $clasificaciones->count(),
                'clasificaciones_ids' => $clasificaciones->pluck('id')->toArray(),
                'clasificaciones_codigos' => $clasificaciones->pluck('cod')->toArray(),
                'clasificaciones_tipos' => $clasificaciones->pluck('tipo')->toArray()
            ];

            // Construir el árbol jerárquico
            $arbol = $this->construirArbolClasificaciones($clasificaciones);

            // Preparar respuesta con información de la dependencia
            $response = [
                'dependencia' => [
                    'id' => $dependencia->id,
                    'nom_organico' => $dependencia->nom_organico,
                    'cod_organico' => $dependencia->cod_organico,
                    'tipo' => $dependencia->tipo
                ],
                'clasificaciones' => $arbol,
                'debug' => $debugInfo  // Información temporal de depuración
            ];

            return $this->successResponse($response, 'Clasificaciones por dependencia obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las clasificaciones por dependencia: ' . $e->getMessage());
        }
    }

    /**
     * Construye el árbol jerárquico de clasificaciones documentales.
     *
     * Este método organiza las clasificaciones en una estructura de árbol
     * basada en las relaciones padre-hijo, formateando los nombres con el tipo.
     *
     * @param \Illuminate\Database\Eloquent\Collection $clasificaciones Colección de clasificaciones
     * @param int|null $parentId ID del elemento padre (null para elementos raíz)
     * @return array Estructura de árbol jerárquico
     */
    private function construirArbolClasificaciones($clasificaciones, $parentId = null)
    {
        $arbol = [];

        foreach ($clasificaciones as $clasificacion) {
            // Solo procesar elementos del nivel actual
            if ($clasificacion->parent == $parentId) {
                $elemento = [
                    'id' => $clasificacion->id,
                    'cod' => $clasificacion->cod,
                    'nom' => $clasificacion->nom,
                    'tipo' => $clasificacion->tipo,
                    'a_g' => $clasificacion->a_g,
                    'a_c' => $clasificacion->a_c,
                    'ct' => $clasificacion->ct,
                    'e' => $clasificacion->e,
                    'm_d' => $clasificacion->m_d,
                    's' => $clasificacion->s,
                    'procedimiento' => $clasificacion->procedimiento,
                    'children' => $this->construirArbolClasificaciones($clasificaciones, $clasificacion->id)
                ];

                $arbol[] = $elemento;
            }
        }

        return $arbol;
    }

    /**
     * Normaliza el tipo de elemento a formato estándar.
     *
     * @param string $tipo Tipo de elemento
     * @return string Tipo normalizado
     */
    private function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));

        if (empty($tipo)) {
            return '';
        }

        $mapeo = [
            'serie' => 'Serie',
            'subserie' => 'SubSerie',
            'sub-serie' => 'SubSerie',
            'sub serie' => 'SubSerie',
            'tipodocumento' => 'TipoDocumento',
            'tipo documento' => 'TipoDocumento',
            'tipo-documento' => 'TipoDocumento',
            'td' => 'TipoDocumento',
            'ss' => 'SubSerie',
            's' => 'Serie'
        ];

        // Si está en el mapeo, retornar el valor normalizado
        if (isset($mapeo[$tipo])) {
            return $mapeo[$tipo];
        }

        // Intentar coincidencia parcial
        foreach ($mapeo as $key => $value) {
            if (strpos($tipo, $key) !== false || strpos($key, $tipo) !== false) {
                return $value;
            }
        }

        // Si no se encuentra, intentar capitalizar y verificar
        $tipoCapitalizado = ucfirst($tipo);
        if (in_array($tipoCapitalizado, ['Serie', 'SubSerie', 'TipoDocumento'])) {
            return $tipoCapitalizado;
        }

        // Retornar vacío si no se puede normalizar
        return '';
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
}
