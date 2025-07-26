<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigDiviPoliRequest;
use App\Http\Requests\Configuracion\UpdateConfigDiviPoliRequest;
use App\Http\Requests\Configuracion\ListConfigDiviPoliRequest;
use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigDiviPoliController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las divisiones políticas del sistema.
     *
     * Este método retorna todas las divisiones políticas con sus relaciones
     * jerárquicas (padre e hijos). Es útil para interfaces de administración
     * donde se necesita mostrar la estructura completa de división política.
     *
     * @param ListConfigDiviPoliRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de divisiones políticas
     *
     * @queryParam tipo string Filtrar por tipo (Pais, Departamento, Municipio). Example: "Departamento"
     * @queryParam parent integer Filtrar por división política padre. Example: 1
     * @queryParam search string Buscar por nombre o código. Example: "Bogotá"
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de divisiones políticas obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "parent": null,
     *       "codigo": "CO",
     *       "nombre": "Colombia",
     *       "tipo": "Pais",
     *       "parent": null,
     *       "children": [
     *         {
     *           "id": 2,
     *           "codigo": "CUN",
     *           "nombre": "Cundinamarca",
     *           "tipo": "Departamento"
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de divisiones políticas",
     *   "error": "Error message"
     * }
     */
    public function index(ListConfigDiviPoliRequest $request)
    {
        try {
            $query = ConfigDiviPoli::with(['parent', 'children']);

            // Aplicar filtros si se proporcionan
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->validated('tipo'));
            }

            if ($request->filled('parent')) {
                $query->where('parent', $request->validated('parent'));
            }

            if ($request->filled('search')) {
                $search = $request->validated('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            }

            // Ordenar por tipo y nombre
            $query->orderBy('tipo', 'asc')->orderBy('nombre', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->validated('per_page');
                $diviPoli = $query->paginate($perPage);
            } else {
                $diviPoli = $query->get();
            }

            return $this->successResponse($diviPoli, 'Listado de divisiones políticas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de divisiones políticas', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva división política en el sistema.
     *
     * Este método permite crear una nueva división política con validación
     * de datos y verificación de relaciones jerárquicas.
     *
     * @param StoreConfigDiviPoliRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la división política creada
     *
     * @bodyParam parent integer ID de la división política padre (opcional). Example: 1
     * @bodyParam codigo string required Código único de la división política. Example: "BOG"
     * @bodyParam nombre string required Nombre de la división política. Example: "Bogotá D.C."
     * @bodyParam tipo string required Tipo de división política (Pais, Departamento, Municipio). Example: "Municipio"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "División política creada exitosamente",
     *   "data": {
     *     "id": 3,
     *     "parent": 2,
     *     "codigo": "BOG",
     *     "nombre": "Bogotá D.C.",
     *     "tipo": "Municipio",
     *     "parent": {
     *       "id": 2,
     *       "codigo": "CUN",
     *       "nombre": "Cundinamarca"
     *     }
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "codigo": ["El código ya está en uso, por favor elija otro."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la división política",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigDiviPoliRequest $request)
    {
        try {
            DB::beginTransaction();

            $diviPoli = ConfigDiviPoli::create($request->validated());

            DB::commit();

            return $this->successResponse(
                $diviPoli->load(['parent', 'children']),
                'División política creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la división política', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una división política específica por su ID.
     *
     * Este método permite obtener los detalles de una división política específica,
     * incluyendo sus relaciones jerárquicas (padre e hijos).
     *
     * @param ConfigDiviPoli $configDiviPoli La división política a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la división política
     *
     * @urlParam configDiviPoli integer required El ID de la división política. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "División política encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "parent": null,
     *     "codigo": "CO",
     *     "nombre": "Colombia",
     *     "tipo": "Pais",
     *     "children": [
     *       {
     *         "id": 2,
     *         "codigo": "CUN",
     *         "nombre": "Cundinamarca"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "División política no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la división política",
     *   "error": "Error message"
     * }
     */
    public function show(ConfigDiviPoli $configDiviPoli)
    {
        try {
            return $this->successResponse(
                $configDiviPoli->load(['parent', 'children']),
                'División política encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la división política', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una división política existente en el sistema.
     *
     * Este método permite modificar los datos de una división política existente,
     * incluyendo validaciones para evitar referencias circulares en la jerarquía.
     *
     * @param UpdateConfigDiviPoliRequest $request La solicitud HTTP validada
     * @param ConfigDiviPoli $configDiviPoli La división política a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la división política actualizada
     *
     * @bodyParam parent integer ID de la división política padre (opcional). Example: 2
     * @bodyParam codigo string Código único de la división política. Example: "BOG"
     * @bodyParam nombre string Nombre de la división política. Example: "Bogotá D.C."
     * @bodyParam tipo string Tipo de división política (Pais, Departamento, Municipio). Example: "Municipio"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "División política actualizada exitosamente",
     *   "data": {
     *     "id": 3,
     *     "parent": 2,
     *     "codigo": "BOG",
     *     "nombre": "Bogotá D.C.",
     *     "tipo": "Municipio"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "parent": ["Una división política no puede ser su propio padre."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la división política",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigDiviPoliRequest $request, ConfigDiviPoli $configDiviPoli)
    {
        try {
            DB::beginTransaction();

            $configDiviPoli->update($request->validated());

            DB::commit();

            return $this->successResponse(
                $configDiviPoli->load(['parent', 'children']),
                'División política actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la división política', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una división política del sistema.
     *
     * Este método permite eliminar una división política específica, verificando
     * que no tenga divisiones políticas hijas asociadas antes de proceder.
     *
     * @param ConfigDiviPoli $configDiviPoli La división política a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam configDiviPoli integer required El ID de la división política a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "División política eliminada exitosamente"
     * }
     *
     * @response 409 {
     *   "status": false,
     *   "message": "No se puede eliminar porque tiene divisiones políticas asociadas"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la división política",
     *   "error": "Error message"
     * }
     */
    public function destroy(ConfigDiviPoli $configDiviPoli)
    {
        try {
            DB::beginTransaction();

            // Verificar si tiene dependencias (hijos)
            if ($configDiviPoli->children()->exists()) {
                return $this->errorResponse(
                    'No se puede eliminar porque tiene divisiones políticas asociadas',
                    null,
                    409
                );
            }

            $configDiviPoli->delete();

            DB::commit();

            return $this->successResponse(null, 'División política eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la división política', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado de todos los países del sistema.
     *
     * Este método retorna únicamente las divisiones políticas de tipo "Pais",
     * útiles para formularios de selección de país.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de países
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de países obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "codigo": "CO",
     *       "nombre": "Colombia",
     *       "tipo": "Pais"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de países",
     *   "error": "Error message"
     * }
     */
    public function paises()
    {
        try {
            $paises = ConfigDiviPoli::where('tipo', 'Pais')
                ->orderBy('nombre', 'asc')
                ->get();

            return $this->successResponse($paises, 'Listado de países obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de países', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado de departamentos de un país específico.
     *
     * Este método retorna las divisiones políticas de tipo "Departamento"
     * que pertenecen al país especificado por ID.
     *
     * @param int $paisId El ID del país
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de departamentos
     *
     * @urlParam paisId integer required El ID del país. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de departamentos obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 2,
     *       "codigo": "CUN",
     *       "nombre": "Cundinamarca",
     *       "tipo": "Departamento",
     *       "parent": 1
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "País no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de departamentos",
     *   "error": "Error message"
     * }
     */
    public function departamentos(int $paisId)
    {
        try {
            // Verificar que el país existe
            $pais = ConfigDiviPoli::where('id', $paisId)
                ->where('tipo', 'Pais')
                ->first();

            if (!$pais) {
                return $this->errorResponse('País no encontrado', null, 404);
            }

            $departamentos = ConfigDiviPoli::where('parent', $paisId)
                ->where('tipo', 'Departamento')
                ->orderBy('nombre', 'asc')
                ->get();

            return $this->successResponse($departamentos, 'Listado de departamentos obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de departamentos', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado de municipios de un departamento específico.
     *
     * Este método retorna las divisiones políticas de tipo "Municipio"
     * que pertenecen al departamento especificado por ID.
     *
     * @param int $departamentoId El ID del departamento
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de municipios
     *
     * @urlParam departamentoId integer required El ID del departamento. Example: 2
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de municipios obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 3,
     *       "codigo": "BOG",
     *       "nombre": "Bogotá D.C.",
     *       "tipo": "Municipio",
     *       "parent": 2
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Departamento no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de municipios",
     *   "error": "Error message"
     * }
     */
    public function municipios(int $departamentoId)
    {
        try {
            // Verificar que el departamento existe
            $departamento = ConfigDiviPoli::where('id', $departamentoId)
                ->where('tipo', 'Departamento')
                ->first();

            if (!$departamento) {
                return $this->errorResponse('Departamento no encontrado', null, 404);
            }

            $municipios = ConfigDiviPoli::where('parent', $departamentoId)
                ->where('tipo', 'Municipio')
                ->orderBy('nombre', 'asc')
                ->get();

            return $this->successResponse($municipios, 'Listado de municipios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de municipios', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas generales de las divisiones políticas del sistema.
     *
     * Este método proporciona información estadística sobre las divisiones políticas,
     * incluyendo conteos por tipo, estructura jerárquica y distribución geográfica.
     * Útil para dashboards y reportes administrativos.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas de divisiones políticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas de divisiones políticas obtenidas exitosamente",
     *   "data": {
     *     "total_divisiones": 150,
     *     "conteo_por_tipo": {
     *       "Pais": 1,
     *       "Departamento": 32,
     *       "Municipio": 117
     *     },
     *     "estructura_jerarquica": {
     *       "paises_con_departamentos": 1,
     *       "departamentos_con_municipios": 32,
     *       "divisiones_sin_hijos": 117
     *     },
     *     "distribucion_geografica": {
     *       "pais_principal": {
     *         "id": 1,
     *         "nombre": "Colombia",
     *         "total_departamentos": 32,
     *         "total_municipios": 117
     *       },
     *       "departamentos_mas_poblados": [
     *         {
     *           "id": 2,
     *           "nombre": "Cundinamarca",
     *           "total_municipios": 15
     *         }
     *       ]
     *     },
     *     "ultimas_actualizaciones": [
     *       {
     *         "id": 150,
     *         "nombre": "Nuevo Municipio",
     *         "tipo": "Municipio",
     *         "updated_at": "2024-01-15T10:30:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas de divisiones políticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas()
    {
        try {
            // Conteo total de divisiones políticas
            $totalDivisiones = ConfigDiviPoli::count();

            // Conteo por tipo
            $conteoPorTipo = ConfigDiviPoli::selectRaw('tipo, COUNT(*) as total')
                ->groupBy('tipo')
                ->pluck('total', 'tipo')
                ->toArray();

            // Estructura jerárquica
            $paisesConDepartamentos = ConfigDiviPoli::where('tipo', 'Pais')
                ->whereHas('children', function ($query) {
                    $query->where('tipo', 'Departamento');
                })
                ->count();

            $departamentosConMunicipios = ConfigDiviPoli::where('tipo', 'Departamento')
                ->whereHas('children', function ($query) {
                    $query->where('tipo', 'Municipio');
                })
                ->count();

            $divisionesSinHijos = ConfigDiviPoli::whereDoesntHave('children')->count();

            // Distribución geográfica
            $paisPrincipal = ConfigDiviPoli::where('tipo', 'Pais')
                ->withCount(['children as total_departamentos' => function ($query) {
                    $query->where('tipo', 'Departamento');
                }])
                ->withCount(['children as total_municipios' => function ($query) {
                    $query->where('tipo', 'Municipio');
                }])
                ->first();

            // Departamentos con más municipios
            $departamentosMasPoblados = ConfigDiviPoli::where('tipo', 'Departamento')
                ->withCount(['children as total_municipios' => function ($query) {
                    $query->where('tipo', 'Municipio');
                }])
                ->orderByDesc('total_municipios')
                ->limit(5)
                ->get(['id', 'nombre', 'total_municipios']);

            // Últimas actualizaciones
            $ultimasActualizaciones = ConfigDiviPoli::orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(['id', 'nombre', 'tipo', 'updated_at']);

            $estadisticas = [
                'total_divisiones' => $totalDivisiones,
                'conteo_por_tipo' => $conteoPorTipo,
                /* 'estructura_jerarquica' => [
                    'paises_con_departamentos' => $paisesConDepartamentos,
                    'departamentos_con_municipios' => $departamentosConMunicipios,
                    'divisiones_sin_hijos' => $divisionesSinHijos
                ],
                'distribucion_geografica' => [
                    'pais_principal' => $paisPrincipal,
                    'departamentos_mas_poblados' => $departamentosMasPoblados
                ],
                'ultimas_actualizaciones' => $ultimasActualizaciones */
            ];

            return $this->successResponse($estadisticas, 'Estadísticas de divisiones políticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas de divisiones políticas', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado completo de países con sus departamentos y municipios.
     *
     * Este método retorna una estructura jerárquica completa de todos los países
     * con sus departamentos y municipios anidados. Es útil para interfaces que
     * necesitan mostrar la estructura geográfica completa del sistema.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la estructura jerárquica completa
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estructura jerárquica de países obtenida exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "codigo": "CO",
     *       "nombre": "Colombia",
     *       "tipo": "Pais",
     *       "departamentos": [
     *         {
     *           "id": 2,
     *           "codigo": "CUN",
     *           "nombre": "Cundinamarca",
     *           "tipo": "Departamento",
     *           "municipios": [
     *             {
     *               "id": 3,
     *               "codigo": "BOG",
     *               "nombre": "Bogotá D.C.",
     *               "tipo": "Municipio"
     *             },
     *             {
     *               "id": 4,
     *               "codigo": "SOA",
     *               "nombre": "Soacha",
     *               "tipo": "Municipio"
     *             }
     *           ]
     *         },
     *         {
     *           "id": 5,
     *           "codigo": "ANT",
     *           "nombre": "Antioquia",
     *           "tipo": "Departamento",
     *           "municipios": [
     *             {
     *               "id": 6,
     *               "codigo": "MED",
     *               "nombre": "Medellín",
     *               "tipo": "Municipio"
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
     *   "message": "Error al obtener la estructura jerárquica de países",
     *   "error": "Error message"
     * }
     */
    public function diviPoliCompleta()
    {
        try {
            $paises = ConfigDiviPoli::where('tipo', 'Pais')
                ->with(['children' => function ($query) {
                    $query->where('tipo', 'Departamento')
                        ->orderBy('nombre', 'asc')
                        ->with(['children' => function ($subQuery) {
                            $subQuery->where('tipo', 'Municipio')
                                ->orderBy('nombre', 'asc');
                        }]);
                }])
                ->orderBy('nombre', 'asc')
                ->get()
                ->map(function ($pais) {
                    return [
                        'id' => $pais->id,
                        'codigo' => $pais->codigo,
                        'nombre' => $pais->nombre,
                        'tipo' => $pais->tipo,
                        'departamentos' => $pais->children->map(function ($departamento) {
                            return [
                                'id' => $departamento->id,
                                'codigo' => $departamento->codigo,
                                'nombre' => $departamento->nombre,
                                'tipo' => $departamento->tipo,
                                'municipios' => $departamento->children->map(function ($municipio) {
                                    return [
                                        'id' => $municipio->id,
                                        'codigo' => $municipio->codigo,
                                        'nombre' => $municipio->nombre,
                                        'tipo' => $municipio->tipo
                                    ];
                                })
                            ];
                        })
                    ];
                });

            return $this->successResponse($paises, 'Estructura jerárquica de países obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la estructura jerárquica de países', $e->getMessage(), 500);
        }
    }
}
