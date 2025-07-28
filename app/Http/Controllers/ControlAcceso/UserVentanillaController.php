<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ControlAcceso\UserVentanilla;
use App\Http\Requests\ControlAcceso\StoreUserVentanillaRequest;
use App\Http\Requests\ControlAcceso\UpdateUserVentanillaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserVentanillaController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las asignaciones de ventanillas a usuarios.
     *
     * Este método retorna todas las asignaciones de ventanillas a usuarios
     * con información detallada de usuarios y ventanillas. Es útil para
     * interfaces de administración donde se necesita mostrar la distribución
     * de ventanillas por usuario.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de asignaciones
     *
     * @queryParam user_id integer Filtrar por ID de usuario. Example: 1
     * @queryParam ventanilla_id integer Filtrar por ID de ventanilla. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de asignaciones de ventanillas obtenido exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "user_id": 1,
     *         "ventanilla_id": 1,
     *         "created_at": "2024-01-01T00:00:00.000000Z",
     *         "updated_at": "2024-01-01T00:00:00.000000Z",
     *         "user": {
     *           "id": 1,
     *           "nombres": "Juan",
     *           "apellidos": "Pérez",
     *           "email": "juan.perez@example.com"
     *         },
     *         "ventanilla": {
     *           "id": 1,
     *           "nombre": "Ventanilla Principal",
     *           "descripcion": "Ventanilla principal del sistema"
     *         }
     *       }
     *     ],
     *     "per_page": 15,
     *     "total": 1
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de asignaciones",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = UserVentanilla::with(['user', 'ventanilla']);

            // Aplicar filtros si se proporcionan
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('ventanilla_id')) {
                $query->where('ventanilla_id', $request->ventanilla_id);
            }

            // Ordenar por fecha de creación
            $query->orderBy('created_at', 'desc');

            // Paginar
            $perPage = $request->get('per_page', 15);
            $asignaciones = $query->paginate($perPage);

            return $this->successResponse($asignaciones, 'Listado de asignaciones de ventanillas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de asignaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva asignación de ventanilla a usuario.
     *
     * Este método permite asignar una ventanilla específica a un usuario.
     * Verifica que tanto el usuario como la ventanilla existan y que no
     * haya una asignación duplicada.
     *
     * @param StoreUserVentanillaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la asignación creada
     *
     * @bodyParam user_id integer required ID del usuario. Example: 1
     * @bodyParam ventanilla_id integer required ID de la ventanilla. Example: 1
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Asignación de ventanilla creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "ventanilla_id": 1,
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "user": {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez"
     *     },
     *     "ventanilla": {
     *       "id": 1,
     *       "nombre": "Ventanilla Principal"
     *     }
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "user_id": ["El usuario seleccionado no existe en el sistema."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la asignación",
     *   "error": "Error message"
     * }
     */
    public function store(StoreUserVentanillaRequest $request)
    {
        try {
            DB::beginTransaction();

            // Verificar si ya existe la asignación
            $existingAssignment = UserVentanilla::where('user_id', $request->validated('user_id'))
                ->where('ventanilla_id', $request->validated('ventanilla_id'))
                ->first();

            if ($existingAssignment) {
                return $this->errorResponse(
                    'El usuario ya tiene asignada esta ventanilla',
                    null,
                    409
                );
            }

            $asignacion = UserVentanilla::create($request->validated());

            DB::commit();

            return $this->successResponse(
                $asignacion->load(['user', 'ventanilla']),
                'Asignación de ventanilla creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la asignación', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una asignación específica de ventanilla a usuario.
     *
     * Este método permite obtener los detalles de una asignación específica,
     * incluyendo la información del usuario y la ventanilla asignada.
     *
     * @param UserVentanilla $userVentanilla La asignación a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la asignación
     *
     * @urlParam userVentanilla integer required El ID de la asignación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Asignación encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "ventanilla_id": 1,
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "user": {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez"
     *     },
     *     "ventanilla": {
     *       "id": 1,
     *       "nombre": "Ventanilla Principal"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Asignación no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la asignación",
     *   "error": "Error message"
     * }
     */
    public function show(UserVentanilla $userVentanilla)
    {
        try {
            return $this->successResponse(
                $userVentanilla->load(['user', 'ventanilla']),
                'Asignación encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la asignación', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una asignación de ventanilla a usuario.
     *
     * Este método permite modificar una asignación existente, cambiando
     * el usuario o la ventanilla asignada.
     *
     * @param UpdateUserVentanillaRequest $request La solicitud HTTP validada
     * @param UserVentanilla $userVentanilla La asignación a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la asignación actualizada
     *
     * @bodyParam user_id integer ID del usuario. Example: 2
     * @bodyParam ventanilla_id integer ID de la ventanilla. Example: 2
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Asignación actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 2,
     *     "ventanilla_id": 2,
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "user": {
     *       "id": 2,
     *       "nombres": "María",
     *       "apellidos": "García"
     *     },
     *     "ventanilla": {
     *       "id": 2,
     *       "nombre": "Ventanilla Secundaria"
     *     }
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "ventanilla_id": ["La ventanilla seleccionada no existe en el sistema."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la asignación",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateUserVentanillaRequest $request, UserVentanilla $userVentanilla)
    {
        try {
            DB::beginTransaction();

            // Verificar si la nueva asignación ya existe
            if ($request->filled('user_id') && $request->filled('ventanilla_id')) {
                $existingAssignment = UserVentanilla::where('user_id', $request->validated('user_id'))
                    ->where('ventanilla_id', $request->validated('ventanilla_id'))
                    ->where('id', '!=', $userVentanilla->id)
                    ->first();

                if ($existingAssignment) {
                    return $this->errorResponse(
                        'El usuario ya tiene asignada esta ventanilla',
                        null,
                        409
                    );
                }
            }

            $userVentanilla->update($request->validated());

            DB::commit();

            return $this->successResponse(
                $userVentanilla->load(['user', 'ventanilla']),
                'Asignación actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la asignación', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una asignación de ventanilla a usuario.
     *
     * Este método permite eliminar una asignación específica, liberando
     * la ventanilla del usuario asignado.
     *
     * @param UserVentanilla $userVentanilla La asignación a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam userVentanilla integer required El ID de la asignación a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Asignación eliminada exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la asignación",
     *   "error": "Error message"
     * }
     */
    public function destroy(UserVentanilla $userVentanilla)
    {
        try {
            DB::beginTransaction();

            $userVentanilla->delete();

            DB::commit();

            return $this->successResponse(null, 'Asignación eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la asignación', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas detalladas sobre las asignaciones de ventanillas a usuarios.
     *
     * Este método proporciona información estadística útil sobre las asignaciones
     * de ventanillas, incluyendo totales, distribución por usuario y ventanilla,
     * y análisis de actividad reciente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_asignaciones": 25,
     *     "usuarios_con_asignaciones": 15,
     *     "ventanillas_asignadas": 8,
     *     "asignaciones_este_mes": 5,
     *     "asignaciones_este_anio": 20,
     *     "usuarios_mas_asignaciones": [
     *       {
     *         "user_id": 1,
     *         "nombres": "Juan",
     *         "apellidos": "Pérez",
     *         "total_asignaciones": 3
     *       }
     *     ],
     *     "ventanillas_mas_populares": [
     *       {
     *         "ventanilla_id": 1,
     *         "nombre": "Ventanilla Principal",
     *         "total_asignaciones": 5
     *       }
     *     ],
     *     "distribucion_por_mes": [
     *       {
     *         "mes": "Enero 2024",
     *         "total": 8
     *       }
     *     ],
     *     "asignaciones_recientes": [
     *       {
     *         "id": 1,
     *         "created_at": "2024-01-15T10:00:00.000000Z",
     *         "user": {
     *           "id": 1,
     *           "nombres": "Juan",
     *           "apellidos": "Pérez"
     *         },
     *         "ventanilla": {
     *           "id": 1,
     *           "nombre": "Ventanilla Principal"
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
            $totalAsignaciones = UserVentanilla::count();
            $usuariosConAsignaciones = UserVentanilla::distinct('user_id')->count();
            $ventanillasAsignadas = UserVentanilla::distinct('ventanilla_id')->count();

            // Asignaciones por período
            $asignacionesEsteMes = UserVentanilla::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $asignacionesEsteAnio = UserVentanilla::whereYear('created_at', now()->year)
                ->count();

            // Usuarios con más asignaciones
            $usuariosMasAsignaciones = UserVentanilla::select('user_id')
                ->selectRaw('COUNT(*) as total_asignaciones')
                ->with('user:id,nombres,apellidos')
                ->groupBy('user_id')
                ->orderByDesc('total_asignaciones')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'user_id' => $item->user_id,
                        'nombres' => $item->user->nombres,
                        'apellidos' => $item->user->apellidos,
                        'total_asignaciones' => $item->total_asignaciones
                    ];
                });

            // Ventanillas más populares
            $ventanillasMasPopulares = UserVentanilla::select('ventanilla_id')
                ->selectRaw('COUNT(*) as total_asignaciones')
                ->with('ventanilla:id,nombre')
                ->groupBy('ventanilla_id')
                ->orderByDesc('total_asignaciones')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'ventanilla_id' => $item->ventanilla_id,
                        'nombre' => $item->ventanilla->nombre,
                        'total_asignaciones' => $item->total_asignaciones
                    ];
                });

            // Distribución por mes (últimos 12 meses)
            $distribucionPorMes = [];
            for ($i = 11; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $total = UserVentanilla::whereYear('created_at', $fecha->year)
                    ->whereMonth('created_at', $fecha->month)
                    ->count();

                $distribucionPorMes[] = [
                    'mes' => $fecha->format('F Y'),
                    'total' => $total
                ];
            }

            // Asignaciones más recientes
            $asignacionesRecientes = UserVentanilla::with(['user:id,nombres,apellidos', 'ventanilla:id,nombre'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($asignacion) {
                    return [
                        'id' => $asignacion->id,
                        'created_at' => $asignacion->created_at->format('Y-m-d H:i:s'),
                        'user' => [
                            'id' => $asignacion->user->id,
                            'nombres' => $asignacion->user->nombres,
                            'apellidos' => $asignacion->user->apellidos
                        ],
                        'ventanilla' => [
                            'id' => $asignacion->ventanilla->id,
                            'nombre' => $asignacion->ventanilla->nombre
                        ]
                    ];
                });

            $estadisticas = [
                'total_asignaciones' => $totalAsignaciones,
                'usuarios_con_asignaciones' => $usuariosConAsignaciones,
                'ventanillas_asignadas' => $ventanillasAsignadas,
                'asignaciones_este_mes' => $asignacionesEsteMes,
                'asignaciones_este_anio' => $asignacionesEsteAnio,
                'usuarios_mas_asignaciones' => $usuariosMasAsignaciones,
                'ventanillas_mas_populares' => $ventanillasMasPopulares,
                'distribucion_por_mes' => $distribucionPorMes,
                'asignaciones_recientes' => $asignacionesRecientes
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
