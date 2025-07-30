<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlAcceso\AsignarCargoRequest;
use App\Http\Requests\ControlAcceso\FinalizarCargoRequest;
use App\Http\Requests\ControlAcceso\ListUserCargosRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCargoController extends Controller
{
    use ApiResponseTrait;

    /**
     * Lista las asignaciones de cargos con filtros opcionales.
     *
     * @param ListUserCargosRequest $request
     * @return JsonResponse
     */
    public function index(ListUserCargosRequest $request): JsonResponse
    {
        try {
            $query = UserCargo::with(['user:id,nombres,apellidos,email', 'cargo:id,nom_organico,cod_organico,tipo']);

            // Aplicar filtros
            if ($request->user_id) {
                $query->delUsuario($request->user_id);
            }

            if ($request->organigrama_id) {
                $query->delCargo($request->organigrama_id);
            }

            if ($request->has('estado') && $request->estado !== null) {
                if ($request->estado) {
                    $query->activos();
                } else {
                    $query->finalizados();
                }
            }

            if ($request->fecha_desde) {
                $query->where('fecha_inicio', '>=', $request->fecha_desde);
            }

            if ($request->fecha_hasta) {
                $query->where('fecha_inicio', '<=', $request->fecha_hasta);
            }

            if (!$request->incluir_finalizados) {
                $query->activos();
            }

            // Ordenamiento
            $query->orderBy($request->sort_by, $request->sort_order);

            // Paginación
            $asignaciones = $query->paginate($request->per_page);

            // Los datos ya incluyen la información adicional a través del modelo

            // Estadísticas generales
            $estadisticas = [
                'total_asignaciones' => UserCargo::count(),
                'asignaciones_activas' => UserCargo::activos()->count(),
                'asignaciones_finalizadas' => UserCargo::finalizados()->count(),
                'usuarios_con_cargo' => UserCargo::activos()->distinct('user_id')->count(),
                'cargos_ocupados' => UserCargo::activos()->distinct('organigrama_id')->count()
            ];

            $data = [
                'asignaciones' => $asignaciones,
                'estadisticas' => $estadisticas,
                'filtros_aplicados' => $request->only([
                    'user_id',
                    'organigrama_id',
                    'estado',
                    'fecha_desde',
                    'fecha_hasta',
                    'incluir_finalizados',
                    'sort_by',
                    'sort_order'
                ])
            ];

            return $this->successResponse($data, 'Lista de asignaciones de cargos obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las asignaciones de cargos', $e->getMessage(), 500);
        }
    }

    /**
     * Asigna un cargo a un usuario.
     *
     * @param AsignarCargoRequest $request
     * @return JsonResponse
     */
    public function asignarCargo(AsignarCargoRequest $request): JsonResponse
    {
        try {
            $user = User::findOrFail($request->user_id);
            $cargo = CalidadOrganigrama::findOrFail($request->organigrama_id);

            // Verificar que es un cargo válido
            if (!$cargo->puedeAsignarUsuarios()) {
                return $this->errorResponse(
                    'Error en la asignación',
                    'El elemento seleccionado no es un cargo válido',
                    422
                );
            }

            // Asignar el cargo
            $asignacion = $user->asignarCargo(
                $request->organigrama_id,
                $request->fecha_inicio,
                $request->observaciones
            );

            $data = [
                'asignacion' => $asignacion->getDetalleCompleto(),
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'tiene_cargo_activo' => $user->tieneCargoActivo()
                ],
                'cargo' => [
                    'id' => $cargo->id,
                    'nombre' => $cargo->nom_organico,
                    'codigo' => $cargo->cod_organico,
                    'jerarquia' => $cargo->getJerarquiaCompleta(),
                    'estadisticas' => $cargo->getEstadisticasAsignaciones()
                ]
            ];

            return $this->successResponse($data, 'Cargo asignado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al asignar el cargo', $e->getMessage(), 500);
        }
    }

    /**
     * Finaliza la asignación de un cargo.
     *
     * @param int $asignacionId
     * @param FinalizarCargoRequest $request
     * @return JsonResponse
     */
    public function finalizarCargo(int $asignacionId, FinalizarCargoRequest $request): JsonResponse
    {
        try {
            $asignacion = UserCargo::with(['user', 'cargo'])->findOrFail($asignacionId);

            if (!$asignacion->estaActivo()) {
                return $this->errorResponse(
                    'Error al finalizar',
                    'La asignación del cargo ya está finalizada',
                    422
                );
            }

            $asignacion->finalizar($request->fecha_fin, $request->observaciones);

            $data = [
                'asignacion_finalizada' => $asignacion->getDetalleCompleto(),
                'usuario' => [
                    'id' => $asignacion->user->id,
                    'nombres' => $asignacion->user->nombres,
                    'apellidos' => $asignacion->user->apellidos,
                    'tiene_cargo_activo' => $asignacion->user->tieneCargoActivo()
                ]
            ];

            return $this->successResponse($data, 'Cargo finalizado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al finalizar el cargo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el cargo activo de un usuario específico.
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function cargoActivoUsuario(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $cargoActivo = $user->obtenerCargoActivo();

            if (!$cargoActivo) {
                return $this->successResponse(
                    [
                        'usuario' => [
                            'id' => $user->id,
                            'nombres' => $user->nombres,
                            'apellidos' => $user->apellidos,
                            'email' => $user->email
                        ],
                        'cargo_activo' => null,
                        'tiene_cargo_activo' => false
                    ],
                    'El usuario no tiene cargo activo actualmente'
                );
            }

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email
                ],
                'cargo_activo' => $cargoActivo->getDetalleCompleto(),
                'tiene_cargo_activo' => true,
                'duracion_actual_dias' => $cargoActivo->getDuracionEnDias()
            ];

            return $this->successResponse($data, 'Cargo activo del usuario obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el cargo activo del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial de cargos de un usuario.
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function historialUsuario(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $historial = $user->getHistorialCargos();

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email
                ],
                'historial_cargos' => $historial->map(function ($asignacion) {
                    return $asignacion->getDetalleCompleto();
                }),
                'estadisticas' => [
                    'total_asignaciones' => $historial->count(),
                    'asignaciones_activas' => $historial->where('estado', true)->count(),
                    'asignaciones_finalizadas' => $historial->where('estado', false)->count(),
                    'cargo_actual' => $user->tieneCargoActivo() ? $user->cargoActivo->cargo->nom_organico : null
                ]
            ];

            return $this->successResponse($data, 'Historial de cargos del usuario obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los usuarios asignados a un cargo específico.
     *
     * @param int $cargoId
     * @param Request $request
     * @return JsonResponse
     */
    public function usuariosCargo(int $cargoId, Request $request): JsonResponse
    {
        try {
            $cargo = CalidadOrganigrama::findOrFail($cargoId);

            if (!$cargo->puedeAsignarUsuarios()) {
                return $this->errorResponse(
                    'Error de consulta',
                    'El elemento seleccionado no es un cargo válido',
                    422
                );
            }

            $soloActivos = $request->get('solo_activos', true);
            $usuarios = UserCargo::usuariosDelCargo($cargoId, $soloActivos);

            $data = [
                'cargo' => [
                    'id' => $cargo->id,
                    'nombre' => $cargo->nom_organico,
                    'codigo' => $cargo->cod_organico,
                    'jerarquia' => $cargo->getJerarquiaCompleta(),
                    'estadisticas' => $cargo->getEstadisticasAsignaciones()
                ],
                'usuarios_asignados' => $usuarios->map(function ($asignacion) {
                    return $asignacion->getDetalleCompleto();
                }),
                'resumen' => [
                    'total_usuarios' => $usuarios->count(),
                    'usuarios_activos' => $usuarios->where('estado', true)->count(),
                    'filtro_aplicado' => $soloActivos ? 'solo_activos' : 'todos'
                ]
            ];

            return $this->successResponse($data, 'Usuarios del cargo obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los usuarios del cargo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas generales de asignaciones de cargos.
     *
     * @return JsonResponse
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = [
                'resumen_general' => [
                    'total_asignaciones' => UserCargo::count(),
                    'asignaciones_activas' => UserCargo::activos()->count(),
                    'asignaciones_finalizadas' => UserCargo::finalizados()->count(),
                    'usuarios_con_cargo' => UserCargo::activos()->distinct('user_id')->count(),
                    'usuarios_sin_cargo' => User::whereDoesntHave('cargoActivo')->count(),
                    'cargos_ocupados' => UserCargo::activos()->distinct('organigrama_id')->count(),
                    'cargos_disponibles' => CalidadOrganigrama::disponibles()->count()
                ],
                'por_tipo_organigrama' => CalidadOrganigrama::selectRaw('
                    tipo,
                    COUNT(*) as total_elementos,
                    COUNT(CASE WHEN EXISTS(
                        SELECT 1 FROM users_cargos uc
                        WHERE uc.organigrama_id = calidad_organigrama.id
                        AND uc.estado = true
                        AND uc.fecha_fin IS NULL
                    ) THEN 1 END) as elementos_ocupados
                ')
                    ->groupBy('tipo')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->tipo => [
                            'total' => $item->total_elementos,
                            'ocupados' => $item->elementos_ocupados,
                            'disponibles' => $item->total_elementos - $item->elementos_ocupados,
                            'porcentaje_ocupacion' => $item->total_elementos > 0
                                ? round(($item->elementos_ocupados / $item->total_elementos) * 100, 2)
                                : 0
                        ]];
                    }),
                'top_cargos_mas_rotacion' => UserCargo::selectRaw('
                    organigrama_id,
                    COUNT(*) as total_asignaciones,
                    COUNT(CASE WHEN estado = false THEN 1 END) as asignaciones_finalizadas
                ')
                    ->with('cargo:id,nom_organico,cod_organico')
                    ->groupBy('organigrama_id')
                    ->having('total_asignaciones', '>', 1)
                    ->orderByDesc('total_asignaciones')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'cargo' => $item->cargo,
                            'total_asignaciones' => $item->total_asignaciones,
                            'asignaciones_finalizadas' => $item->asignaciones_finalizadas,
                            'rotacion_porcentaje' => $item->total_asignaciones > 0
                                ? round(($item->asignaciones_finalizadas / $item->total_asignaciones) * 100, 2)
                                : 0
                        ];
                    })
            ];

            return $this->successResponse($estadisticas, 'Estadísticas de asignaciones obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Lista todos los cargos disponibles para asignación.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cargosDisponibles(Request $request): JsonResponse
    {
        try {
            $incluirOcupados = $request->get('incluir_ocupados', false);

            $query = CalidadOrganigrama::cargosAsignables()
                ->with(['usuariosActivos.user:id,nombres,apellidos']);

            if (!$incluirOcupados) {
                $query->disponibles();
            }

            $cargos = $query->get()->map(function ($cargo) {
                return [
                    'id' => $cargo->id,
                    'nombre' => $cargo->nom_organico,
                    'codigo' => $cargo->cod_organico,
                    'jerarquia' => $cargo->getJerarquiaCompleta(),
                    'disponible' => !$cargo->tieneUsuariosAsignados(),
                    'usuario_activo' => $cargo->tieneUsuariosAsignados()
                        ? $cargo->getUsuarioActivo()->user
                        : null,
                    'estadisticas' => $cargo->getEstadisticasAsignaciones()
                ];
            });

            $data = [
                'cargos' => $cargos,
                'resumen' => [
                    'total_cargos' => $cargos->count(),
                    'cargos_disponibles' => $cargos->where('disponible', true)->count(),
                    'cargos_ocupados' => $cargos->where('disponible', false)->count(),
                    'filtro_aplicado' => $incluirOcupados ? 'todos' : 'solo_disponibles'
                ]
            ];

            return $this->successResponse($data, 'Lista de cargos obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los cargos disponibles', $e->getMessage(), 500);
        }
    }
}
