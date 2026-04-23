<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Http\Requests\ControlAcceso\StoreUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserProfileRequest;
use App\Http\Requests\ControlAcceso\UpdatePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\ControlAcceso\UserService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserService $service
    ) {}

    /**
     * Listado de usuarios.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $users = $this->service->getAll($filters);

            return $this->successResponse($users, 'Listado de usuarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo usuario.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $validated = $request->validated();
            
            if (isset($validated['estado'])) {
                $validated['estado'] = filter_var($validated['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $user = $this->service->create($validated);

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un usuario específico.
     */
    public function show(string $id)
    {
        try {
            $user = $this->service->getById($id);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse($user, 'Usuario encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un usuario.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        try {
            $validated = $request->validated();

            if (isset($validated['estado'])) {
                $validated['estado'] = filter_var($validated['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $user = $this->service->update($id, $validated);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un usuario.
     */
    public function destroy(string $id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse(null, 'Usuario eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Estadísticas.
     */
    public function estadisticas()
    {
        try {
            return $this->successResponse(
                $this->service->getStats(),
                'Estadísticas obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza perfil del usuario autenticado.
     */
    public function updateUserProfile(UpdateUserProfileRequest $request)
    {
        try {
            $user = Auth::user();
            $user->update($request->validated());

            return $this->successResponse(
                $user->append(['avatar_url', 'firma_url']),
                'Perfil actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el perfil', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza contraseña.
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Usuario no autenticado', null, 401);
            }

            if (!Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'La contraseña actual que ingresaste no es correcta.',
                ]);
            }

            $user->forceFill(['password' => Hash::make($request->validated('password'))])->save();
            
            // Regenerar sesión después de cambiar contraseña
            $request->session()->regenerate();

            return $this->successResponse(null, 'Contraseña actualizada exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos de validación incorrectos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la contraseña', $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }

    /**
     * Obtiene el perfil completo del usuario con toda la información.
     * Optimizado: eager loading para evitar N+1.
     */
    public function getPerfilCompleto(string $id)
    {
        try {
            $user = User::with(['roles', 'sedes', 'cargoActivo.cargo'])->find($id);
            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse([
                'user' => $user,
                'cargo' => $user->cargoActivo?->cargo,
                'stats' => [
                    'total_sedes' => $user->sedes->count(),
                    'total_roles' => $user->roles->count(),
                ]
            ], 'Perfil completo obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el perfil', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los permisos del usuario.
     */
    public function getPermisos(string $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $roles = $user->roles()->with('permissions')->get();
            $allPermissions = collect();
            
            foreach ($roles as $role) {
                $allPermissions = $allPermissions->merge($role->permissions);
            }

            $permisosPorModulo = $allPermissions->groupBy(function ($permission) {
                $parts = explode(' - ', $permission->name);
                return $parts[0] ?? 'Otros';
            });

            return $this->successResponse([
                'roles' => $roles,
                'permisos' => $allPermissions->unique('id')->values(),
                'permisos_por_modulo' => $permisosPorModulo->toArray()
            ], 'Permisos obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener permisos', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial de cambios del usuario.
     */
    public function getHistorial(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);
            $tipo = $request->input('tipo', 'all');

            $cambios = collect([]);

            if ($tipo === 'all' || $tipo === 'cargos') {
                $cargos = $user->cargos()->get();
                foreach ($cargos as $cargo) {
                    $pivotCreated = $cargo->pivot->created_at ?? $cargo->pivot->created_at ?? now();
                    $cambios->push([
                        'tipo' => 'cargo',
                        'descripcion' => 'Cargo asignado: ' . $cargo->nom_organico,
                        'fecha' => $pivotCreated,
                        'detalle' => $cargo
                    ]);
                }
            }

            if ($tipo === 'all' || $tipo === 'sedes') {
                $sedes = $user->sedes()->get();
                foreach ($sedes as $sede) {
                    $pivotCreated = $sede->pivot->created_at ?? $sede->pivot->created_at ?? now();
                    $cambios->push([
                        'tipo' => 'sede',
                        'descripcion' => 'Sede asignada: ' . $sede->nom_sede,
                        'fecha' => $pivotCreated,
                        'detalle' => $sede
                    ]);
                }
            }

            if ($tipo === 'all' || $tipo === 'roles') {
                $roles = $user->roles()->get();
                foreach ($roles as $rol) {
                    $pivotCreated = $rol->pivot->created_at ?? $rol->pivot->created_at ?? now();
                    $cambios->push([
                        'tipo' => 'rol',
                        'descripcion' => 'Rol asignado: ' . $rol->name,
                        'fecha' => $pivotCreated,
                        'detalle' => $rol
                    ]);
                }
            }

            return $this->successResponse([
                'cambios' => $cambios->sortByDesc('fecha')->values()
            ], 'Historial obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener historial', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene las conexiones/sesiones del usuario.
     */
    public function getConexiones(string $id)
    {
        try {
            $user = User::findOrFail($id);
            
            $conexiones = $user->sessions()->orderBy('last_login_at', 'desc')->limit(10)->get();

            return $this->successResponse([
                'conexiones' => $conexiones,
                'total' => $user->sessions()->count()
            ], 'Conexiones obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener conexiones', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene la actividad del usuario.
     */
    public function getActividad(string $id)
    {
        try {
            $user = User::findOrFail($id);

            $sesiones = $user->sessions()->orderBy('last_login_at', 'desc')->limit(20)->get();

            $actividades = $sesiones->map(function ($sesion) {
                return [
                    'tipo' => 'login',
                    'descripcion' => 'Sesión iniciada',
                    'fecha' => $sesion->last_login_at,
                    'detalles' => $sesion->browser . ' en ' . $sesion->device_type,
                    'ip' => $sesion->ip_address
                ];
            });

            return $this->successResponse([
                'actividades' => $actividades,
                'ultimo_acceso' => $user->last_login_at,
                'total_sesiones' => $user->sessions()->count(),
                'created_at' => $user->created_at
            ], 'Actividad obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener actividad', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el cargo actual del usuario.
     */
    public function getUserCargo(string $id)
    {
        try {
            $user = User::findOrFail($id);
            $cargo = $user->cargo;

            return $this->successResponse($cargo, 'Cargo obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener cargo', $e->getMessage(), 500);
        }
    }
}