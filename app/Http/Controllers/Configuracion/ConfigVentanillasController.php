<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigVentanillaRequest;
use App\Http\Requests\Configuracion\UpdateConfigVentanillaRequest;
use App\Models\Configuracion\configVentanilla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigVentanillasController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las ventanillas del sistema.
     *
     * Este método retorna todas las ventanillas registradas en el sistema
     * con sus relaciones de sede asociadas. Es útil para interfaces de
     * administración donde se necesita mostrar la configuración de ventanillas.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de ventanillas
     *
     * @queryParam sede_id integer Filtrar por ID de sede. Example: 1
     * @queryParam search string Buscar por nombre o código. Example: "Ventanilla 1"
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de ventanillas obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "sede_id": 1,
     *       "nombre": "Ventanilla Principal",
     *       "descripcion": "Ventanilla principal de atención",
     *       "codigo": "V001",
     *       "estado": 1,
     *       "sede": {
     *         "id": 1,
     *         "nombre": "Sede Principal"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de ventanillas",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = configVentanilla::with('sede');

            // Aplicar filtros si se proporcionan
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Ordenar por código
            $query->orderBy('codigo', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $ventanillas = $query->paginate($perPage);
            } else {
                $ventanillas = $query->get();
            }

            return $this->successResponse($ventanillas, 'Listado de ventanillas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de ventanillas', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva ventanilla en el sistema.
     *
     * Este método permite crear una nueva ventanilla con validación
     * de datos y conversión automática del campo estado.
     *
     * @param StoreConfigVentanillaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la ventanilla creada
     *
     * @bodyParam sede_id integer required ID de la sede asociada. Example: 1
     * @bodyParam nombre string required Nombre de la ventanilla. Example: "Ventanilla Principal"
     * @bodyParam descripcion string optional Descripción de la ventanilla. Example: "Ventanilla principal de atención"
     * @bodyParam codigo string optional Código único de la ventanilla. Example: "V001"
     * @bodyParam estado boolean optional Estado de la ventanilla (activo/inactivo). Example: true
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Ventanilla creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "sede_id": 1,
     *     "nombre": "Ventanilla Principal",
     *     "descripcion": "Ventanilla principal de atención",
     *     "codigo": "V001",
     *     "estado": 1,
     *     "sede": {
     *       "id": 1,
     *       "nombre": "Sede Principal"
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
     *   "message": "Error al crear la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigVentanillaRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $ventanilla = configVentanilla::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $ventanilla->load('sede'),
                'Ventanilla creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una ventanilla específica por su ID.
     *
     * Este método permite obtener los detalles de una ventanilla específica,
     * incluyendo su sede asociada.
     *
     * @param configVentanilla $configVentanilla La ventanilla a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la ventanilla
     *
     * @urlParam configVentanilla integer required El ID de la ventanilla. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanilla encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "sede_id": 1,
     *     "nombre": "Ventanilla Principal",
     *     "descripcion": "Ventanilla principal de atención",
     *     "codigo": "V001",
     *     "estado": 1,
     *     "sede": {
     *       "id": 1,
     *       "nombre": "Sede Principal"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function show(configVentanilla $configVentanilla)
    {
        try {
            return $this->successResponse(
                $configVentanilla->load('sede'),
                'Ventanilla encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una ventanilla existente en el sistema.
     *
     * Este método permite modificar los datos de una ventanilla existente,
     * incluyendo conversión automática del campo estado.
     *
     * @param UpdateConfigVentanillaRequest $request La solicitud HTTP validada
     * @param configVentanilla $configVentanilla La ventanilla a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la ventanilla actualizada
     *
     * @bodyParam sede_id integer ID de la sede asociada. Example: 1
     * @bodyParam nombre string Nombre de la ventanilla. Example: "Ventanilla Principal"
     * @bodyParam descripcion string Descripción de la ventanilla. Example: "Ventanilla principal de atención"
     * @bodyParam codigo string Código único de la ventanilla. Example: "V001"
     * @bodyParam estado boolean Estado de la ventanilla (activo/inactivo). Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanilla actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "sede_id": 1,
     *     "nombre": "Ventanilla Principal",
     *     "descripcion": "Ventanilla principal de atención",
     *     "codigo": "V001",
     *     "estado": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "sede_id": ["La sede seleccionada no existe."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigVentanillaRequest $request, configVentanilla $configVentanilla)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $configVentanilla->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $configVentanilla->load('sede'),
                'Ventanilla actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una ventanilla del sistema.
     *
     * Este método permite eliminar una ventanilla específica del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param configVentanilla $configVentanilla La ventanilla a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam configVentanilla integer required El ID de la ventanilla a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanilla eliminada exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function destroy(configVentanilla $configVentanilla)
    {
        try {
            DB::beginTransaction();

            $configVentanilla->delete();

            DB::commit();

            return $this->successResponse(null, 'Ventanilla eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas detalladas sobre las ventanillas de configuración.
     *
     * Este método proporciona información estadística útil sobre las ventanillas
     * de configuración, incluyendo totales, distribución por sede, análisis
     * de actividad reciente y códigos más utilizados.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_ventanillas": 15,
     *     "ventanillas_activas": 12,
     *     "ventanillas_inactivas": 3,
     *     "sedes_con_ventanillas": 5,
     *     "ventanillas_este_mes": 3,
     *     "ventanillas_este_anio": 10,
     *     "sedes_mas_ventanillas": [
     *       {
     *         "sede_id": 1,
     *         "nombre": "Sede Principal",
     *         "total_ventanillas": 5
     *       }
     *     ],
     *     "codigos_mas_utilizados": [
     *       {
     *         "codigo": "V001",
     *         "total_uso": 3
     *       }
     *     ],
     *     "distribucion_por_mes": [
     *       {
     *         "mes": "Enero 2024",
     *         "total": 5
     *       }
     *     ],
     *     "ventanillas_recientes": [
     *       {
     *         "id": 1,
     *         "codigo": "V001",
     *         "nombre": "Ventanilla Principal",
     *         "created_at": "2024-01-15T10:00:00.000000Z",
     *         "sede": {
     *           "id": 1,
     *           "nombre": "Sede Principal"
     *         }
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
            $totalVentanillas = configVentanilla::count();
            $ventanillasActivas = configVentanilla::where('estado', true)->count();
            $ventanillasInactivas = configVentanilla::where('estado', false)->count();
            $sedesConVentanillas = configVentanilla::distinct('sede_id')->count();

            // Ventanillas por período
            $ventanillasEsteMes = configVentanilla::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $ventanillasEsteAnio = configVentanilla::whereYear('created_at', now()->year)
                ->count();

            // Sedes con más ventanillas
            $sedesMasVentanillas = configVentanilla::select('sede_id')
                ->selectRaw('COUNT(*) as total_ventanillas')
                ->with('sede:id,nombre')
                ->groupBy('sede_id')
                ->orderByDesc('total_ventanillas')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'sede_id' => $item->sede_id,
                        'nombre' => $item->sede->nombre,
                        'total_ventanillas' => $item->total_ventanillas
                    ];
                });

            // Códigos más utilizados (análisis de patrones)
            $codigosMasUtilizados = configVentanilla::select('codigo')
                ->selectRaw('COUNT(*) as total_uso')
                ->groupBy('codigo')
                ->orderByDesc('total_uso')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'codigo' => $item->codigo,
                        'total_uso' => $item->total_uso
                    ];
                });

            // Distribución por mes (últimos 12 meses)
            $distribucionPorMes = [];
            for ($i = 11; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $total = configVentanilla::whereYear('created_at', $fecha->year)
                    ->whereMonth('created_at', $fecha->month)
                    ->count();

                $distribucionPorMes[] = [
                    'mes' => $fecha->format('F Y'),
                    'total' => $total
                ];
            }

            // Ventanillas más recientes
            $ventanillasRecientes = configVentanilla::with('sede:id,nombre')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($ventanilla) {
                    return [
                        'id' => $ventanilla->id,
                        'codigo' => $ventanilla->codigo,
                        'nombre' => $ventanilla->nombre,
                        'estado' => $ventanilla->estado,
                        'created_at' => $ventanilla->created_at->format('Y-m-d H:i:s'),
                        'sede' => [
                            'id' => $ventanilla->sede->id,
                            'nombre' => $ventanilla->sede->nombre
                        ]
                    ];
                });

            // Análisis de códigos por patrón
            $analisisCodigos = configVentanilla::selectRaw('
                SUBSTRING(codigo, 1, 1) as prefijo,
                COUNT(*) as total
            ')
                ->groupBy('prefijo')
                ->orderByDesc('total')
                ->get()
                ->map(function ($item) {
                    return [
                        'prefijo' => $item->prefijo,
                        'total' => $item->total
                    ];
                });

            $estadisticas = [
                'total_ventanillas' => $totalVentanillas,
                'ventanillas_activas' => $ventanillasActivas,
                'ventanillas_inactivas' => $ventanillasInactivas,
                'sedes_con_ventanillas' => $sedesConVentanillas,
                'ventanillas_este_mes' => $ventanillasEsteMes,
                'ventanillas_este_anio' => $ventanillasEsteAnio,
                'sedes_mas_ventanillas' => $sedesMasVentanillas,
                'codigos_mas_utilizados' => $codigosMasUtilizados,
                'distribucion_por_mes' => $distribucionPorMes,
                'ventanillas_recientes' => $ventanillasRecientes,
                'analisis_codigos' => $analisisCodigos
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
