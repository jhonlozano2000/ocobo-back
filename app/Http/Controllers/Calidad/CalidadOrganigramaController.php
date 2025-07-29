<?php

namespace App\Http\Controllers\Calidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\CalidadOrganigramaRequest;
use App\Http\Requests\Calidad\ListCalidadOrganigramaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalidadOrganigramaController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene la estructura organizacional completa del organigrama.
     *
     * Este método devuelve la jerarquía completa del organigrama,
     * incluyendo todas las dependencias, oficinas y cargos organizados
     * en una estructura jerárquica.
     *
     * @param ListCalidadOrganigramaRequest $request La solicitud HTTP con filtros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el organigrama completo
     *
     * @queryParam tipo string optional Filtrar por tipo (Dependencia, Oficina, Cargo). Example: Dependencia
     * @queryParam search string optional Buscar por nombre o código orgánico. Example: "Dirección"
     * @queryParam per_page integer optional Número de elementos por página. Example: 15
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Organigrama obtenido correctamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "tipo": "Dependencia",
     *       "nom_organico": "Dirección General",
     *       "cod_organico": "DG001",
     *       "observaciones": "Dirección principal",
     *       "parent": null,
     *       "children": [
     *         {
     *           "id": 2,
     *           "tipo": "Oficina",
     *           "nom_organico": "Oficina de Atención",
     *           "cod_organico": "OA001",
     *           "parent": 1,
     *           "children": [
     *             {
     *               "id": 3,
     *               "tipo": "Cargo",
     *               "nom_organico": "Director",
     *               "cod_organico": "DIR001",
     *               "parent": 2
     *             }
     *           ]
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el organigrama",
     *   "error": "Error message"
     * }
     */
    public function index(ListCalidadOrganigramaRequest $request)
    {
        try {
            $query = CalidadOrganigrama::dependenciasRaiz()->with('children');

            // Aplicar filtros si se proporcionan
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nom_organico', 'like', "%{$search}%")
                        ->orWhere('cod_organico', 'like', "%{$search}%");
                });
            }

            // Ordenar por nombre
            $query->orderBy('nom_organico', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $organigrama = $query->paginate($request->per_page);
            } else {
                $organigrama = $query->get();
            }

            return $this->successResponse($organigrama, 'Organigrama obtenido correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el organigrama', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo nodo en el organigrama.
     *
     * Este método permite crear un nuevo elemento en el organigrama
     * con validación de reglas jerárquicas y estructura organizacional.
     *
     * @param CalidadOrganigramaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el nodo creado
     *
     * @bodyParam tipo string required Tipo de organismo (Dependencia, Oficina, Cargo). Example: "Oficina"
     * @bodyParam nom_organico string required Nombre del organismo. Example: "Oficina de Atención"
     * @bodyParam cod_organico string optional Código orgánico único. Example: "OA001"
     * @bodyParam observaciones string optional Observaciones adicionales. Example: "Oficina principal"
     * @bodyParam parent integer optional ID del nodo padre. Example: 1
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Nodo creado correctamente",
     *   "data": {
     *     "id": 2,
     *     "tipo": "Oficina",
     *     "nom_organico": "Oficina de Atención",
     *     "cod_organico": "OA001",
     *     "observaciones": "Oficina principal",
     *     "parent": 1,
     *     "created_at": "2024-01-15T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "tipo": ["El tipo debe ser Dependencia, Oficina o Cargo."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el nodo",
     *   "error": "Error message"
     * }
     */
    public function store(CalidadOrganigramaRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $organigrama = CalidadOrganigrama::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $organigrama,
                'Nodo creado correctamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un nodo específico del organigrama.
     *
     * Este método permite obtener los detalles de un nodo específico
     * incluyendo su jerarquía y elementos relacionados.
     *
     * @param CalidadOrganigrama $calidadOrganigrama El nodo a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el nodo
     *
     * @urlParam calidadOrganigrama integer required El ID del nodo. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Nodo obtenido correctamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Dependencia",
     *     "nom_organico": "Dirección General",
     *     "cod_organico": "DG001",
     *     "observaciones": "Dirección principal",
     *     "parent": null,
     *     "children": [
     *       {
     *         "id": 2,
     *         "tipo": "Oficina",
     *         "nom_organico": "Oficina de Atención",
     *         "parent": 1
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Nodo no encontrado"
     * }
     */
    public function show(CalidadOrganigrama $calidadOrganigrama)
    {
        try {
            Log::info('=== MÉTODO SHOW EJECUTÁNDOSE ===');
            $organigrama = $calidadOrganigrama->load(['children']);

            return $this->successResponse($organigrama, 'Nodo obtenido correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un nodo existente en el organigrama.
     *
     * Este método permite actualizar un nodo existente manteniendo
     * la integridad de la estructura jerárquica.
     *
     * @param CalidadOrganigramaRequest $request La solicitud HTTP validada
     * @param CalidadOrganigrama $calidadOrganigrama El nodo a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el nodo actualizado
     *
     * @urlParam calidadOrganigrama integer required El ID del nodo a actualizar. Example: 1
     * @bodyParam tipo string required Tipo de organismo (Dependencia, Oficina, Cargo). Example: "Oficina"
     * @bodyParam nom_organico string required Nombre del organismo. Example: "Oficina de Atención"
     * @bodyParam cod_organico string optional Código orgánico único. Example: "OA001"
     * @bodyParam observaciones string optional Observaciones adicionales. Example: "Oficina principal"
     * @bodyParam parent integer optional ID del nodo padre. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Nodo actualizado correctamente",
     *   "data": {
     *     "id": 1,
     *     "tipo": "Oficina",
     *     "nom_organico": "Oficina de Atención Actualizada",
     *     "cod_organico": "OA001",
     *     "observaciones": "Oficina principal actualizada",
     *     "parent": 1,
     *     "updated_at": "2024-01-15T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Nodo no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el nodo",
     *   "error": "Error message"
     * }
     */
    public function update(CalidadOrganigramaRequest $request, CalidadOrganigrama $calidadOrganigrama)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $calidadOrganigrama->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $calidadOrganigrama,
                'Nodo actualizado correctamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un nodo del organigrama.
     *
     * Este método permite eliminar un nodo solo si no tiene elementos hijos,
     * manteniendo la integridad de la estructura jerárquica.
     *
     * @param CalidadOrganigrama $calidadOrganigrama El nodo a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam calidadOrganigrama integer required El ID del nodo a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Nodo eliminado correctamente"
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "No se puede eliminar el nodo porque tiene subelementos"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Nodo no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el nodo",
     *   "error": "Error message"
     * }
     */
    public function destroy(CalidadOrganigrama $calidadOrganigrama)
    {
        try {
            DB::beginTransaction();

            // Verificar si tiene hijos
            if ($calidadOrganigrama->children()->count() > 0) {
                return $this->errorResponse(
                    'No se puede eliminar el nodo porque tiene subelementos',
                    null,
                    400
                );
            }

            $calidadOrganigrama->delete();

            DB::commit();

            return $this->successResponse(null, 'Nodo eliminado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene solo las dependencias principales del organigrama.
     *
     * Este método devuelve únicamente las dependencias de nivel raíz
     * con sus dependencias hijas, excluyendo oficinas y cargos.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las dependencias
     *
     * @queryParam search string optional Buscar por nombre de dependencia. Example: "Dirección"
     * @queryParam per_page integer optional Número de elementos por página. Example: 15
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Lista de dependencias obtenida",
     *   "data": [
     *     {
     *       "id": 1,
     *       "tipo": "Dependencia",
     *       "nom_organico": "Dirección General",
     *       "cod_organico": "DG001",
     *       "children_dependencias": [
     *         {
     *           "id": 2,
     *           "tipo": "Dependencia",
     *           "nom_organico": "Subdirección",
     *           "parent": 1
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las dependencias",
     *   "error": "Error message"
     * }
     */
    public function listDependencias(Request $request)
    {
        try {
            Log::info('=== MÉTODO LISTDEPENDENCIAS EJECUTÁNDOSE ===');

            // Obtener solo las dependencias raíz (sin padre) con toda la jerarquía de dependencias
            $query = CalidadOrganigrama::dependenciasRaiz()->with('children');
            Log::info('Query dependencias raíz con jerarquía completa creada');

            // Aplicar filtro de búsqueda si se proporciona
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('nom_organico', 'like', "%{$search}%");
            }

            // Ordenar por nombre
            $query->orderBy('nom_organico', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                Log::info('Ejecutando paginate');
                $dependencias = $query->paginate($request->per_page);
            } else {
                Log::info('Ejecutando get');
                $dependencias = $query->get();
            }

            Log::info('Dependencias obtenidas: ' . $dependencias->count());
            Log::info('Primera dependencia: ' . ($dependencias->first() ? $dependencias->first()->nom_organico : 'No hay dependencias'));

            return $this->successResponse($dependencias, 'Lista de dependencias obtenida');
        } catch (\Exception $e) {
            Log::error('Error en listDependencias: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error al obtener las dependencias', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene las oficinas con sus respectivos cargos.
     *
     * Este método devuelve todas las oficinas del organigrama
     * junto con los cargos que pertenecen a cada una.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las oficinas y cargos
     *
     * @queryParam search string optional Buscar por nombre de oficina. Example: "Atención"
     * @queryParam per_page integer optional Número de elementos por página. Example: 15
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Lista de oficinas con sus respectivos cargos obtenida correctamente",
     *   "data": [
     *     {
     *       "id": 2,
     *       "tipo": "Oficina",
     *       "nom_organico": "Oficina de Atención",
     *       "cod_organico": "OA001",
     *       "children_cargos": [
     *         {
     *           "id": 3,
     *           "tipo": "Cargo",
     *           "nom_organico": "Director",
     *           "cod_organico": "DIR001",
     *           "parent": 2
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las oficinas",
     *   "error": "Error message"
     * }
     */
    public function listOficinas(Request $request)
    {
        try {
            $query = CalidadOrganigrama::where('tipo', 'Oficina')
                ->with('childrenCargos');

            // Aplicar filtro de búsqueda si se proporciona
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('nom_organico', 'like', "%{$search}%");
            }

            // Ordenar por nombre
            $query->orderBy('nom_organico', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $oficinasConCargos = $query->paginate($request->per_page);
            } else {
                $oficinasConCargos = $query->get();
            }

            return $this->successResponse(
                $oficinasConCargos,
                'Lista de oficinas con sus respectivos cargos obtenida correctamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las oficinas', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas detalladas del organigrama.
     *
     * Este método proporciona información estadística útil sobre el organigrama,
     * incluyendo totales por tipo, distribución jerárquica y análisis de estructura.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_elementos": 25,
     *     "total_dependencias": 8,
     *     "total_oficinas": 12,
     *     "total_cargos": 5,
     *     "dependencias_raiz": 3,
     *     "oficinas_sin_cargos": 2,
     *     "cargos_sin_oficina": 0,
     *     "distribucion_por_tipo": [
     *       {
     *         "tipo": "Dependencia",
     *         "total": 8,
     *         "porcentaje": 32
     *       }
     *     ],
     *     "elementos_recientes": [
     *       {
     *         "id": 1,
     *         "tipo": "Dependencia",
     *         "nom_organico": "Nueva Dependencia",
     *         "created_at": "2024-01-15T10:00:00.000000Z"
     *       }
     *     ]
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
            // Estadísticas generales
            $totalElementos = CalidadOrganigrama::count();
            $totalDependencias = CalidadOrganigrama::where('tipo', 'Dependencia')->count();
            $totalOficinas = CalidadOrganigrama::where('tipo', 'Oficina')->count();
            $totalCargos = CalidadOrganigrama::where('tipo', 'Cargo')->count();
            $dependenciasRaiz = CalidadOrganigrama::dependenciasRaiz()->count();

            // Análisis de estructura
            $oficinasSinCargos = CalidadOrganigrama::where('tipo', 'Oficina')
                ->whereDoesntHave('childrenCargos')
                ->count();

            $cargosSinOficina = CalidadOrganigrama::where('tipo', 'Cargo')
                ->whereNull('parent')
                ->count();

            // Distribución por tipo
            $distribucionPorTipo = CalidadOrganigrama::select('tipo')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('tipo')
                ->get()
                ->map(function ($item) use ($totalElementos) {
                    return [
                        'tipo' => $item->tipo,
                        'total' => $item->total,
                        'porcentaje' => round(($item->total / $totalElementos) * 100, 1)
                    ];
                });

            // Elementos más recientes
            $elementosRecientes = CalidadOrganigrama::orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($elemento) {
                    return [
                        'id' => $elemento->id,
                        'tipo' => $elemento->tipo,
                        'nom_organico' => $elemento->nom_organico,
                        'cod_organico' => $elemento->cod_organico,
                        'created_at' => $elemento->created_at->format('Y-m-d H:i:s')
                    ];
                });

            // Análisis de códigos orgánicos
            $codigosMasUtilizados = CalidadOrganigrama::select('cod_organico')
                ->whereNotNull('cod_organico')
                ->selectRaw('COUNT(*) as total_uso')
                ->groupBy('cod_organico')
                ->orderByDesc('total_uso')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'cod_organico' => $item->cod_organico,
                        'total_uso' => $item->total_uso
                    ];
                });

            // Análisis de jerarquía
            $nivelesJerarquia = CalidadOrganigrama::selectRaw('
                CASE
                    WHEN parent IS NULL THEN 1
                    WHEN parent IN (SELECT id FROM calidad_organigrama WHERE parent IS NULL) THEN 2
                    ELSE 3
                END as nivel,
                COUNT(*) as total
            ')
                ->groupBy('nivel')
                ->orderBy('nivel')
                ->get()
                ->map(function ($item) {
                    return [
                        'nivel' => $item->nivel,
                        'total' => $item->total,
                        'descripcion' => $item->nivel == 1 ? 'Dependencias Raíz' : ($item->nivel == 2 ? 'Subdependencias' : 'Elementos de Tercer Nivel')
                    ];
                });

            $estadisticas = [
                'total_elementos' => $totalElementos,
                'total_dependencias' => $totalDependencias,
                'total_oficinas' => $totalOficinas,
                'total_cargos' => $totalCargos,
                'dependencias_raiz' => $dependenciasRaiz,
                'oficinas_sin_cargos' => $oficinasSinCargos,
                'cargos_sin_oficina' => $cargosSinOficina,
                'distribucion_por_tipo' => $distribucionPorTipo,
                'elementos_recientes' => $elementosRecientes,
                'codigos_mas_utilizados' => $codigosMasUtilizados,
                'niveles_jerarquia' => $nivelesJerarquia
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
