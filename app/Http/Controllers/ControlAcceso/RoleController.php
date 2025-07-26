<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ControlAcceso\StoreRoleRequest;
use App\Http\Requests\ControlAcceso\UpdateRoleRequest;
use App\Http\Requests\ControlAcceso\ListRoleRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    use ApiResponseTrait;



    /**
     * Obtiene un listado paginado de roles con opciones de filtrado y búsqueda.
     *
     * Este método permite obtener todos los roles del sistema con funcionalidades
     * de paginación, búsqueda por nombre y ordenamiento. Es útil para interfaces
     * de administración donde se necesita mostrar una lista de roles.
     *
     * @param ListRoleRequest $request La solicitud HTTP validada con parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de roles
     *
     * @queryParam search string Término de búsqueda para filtrar roles por nombre. Example: admin
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de roles obtenido exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "Administrador",
     *         "guard_name": "web",
     *         "created_at": "2024-01-01T00:00:00.000000Z",
     *         "updated_at": "2024-01-01T00:00:00.000000Z"
     *       }
     *     ],
     *     "per_page": 15,
     *     "total": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "per_page": ["El número de elementos por página debe ser un número entero."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de roles",
     *   "error": "Error message"
     * }
     */
    public function index(ListRoleRequest $request)
    {
        try {
            $query = Role::query();

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->validated('search') . '%');
            }

            // Ordenar por nombre por defecto
            $query->orderBy('name', 'asc');

            // Paginar si se solicita
            $perPage = $request->validated('per_page') ?? 15;
            $roles = $query->paginate($perPage);

            return $this->successResponse($roles, 'Listado de roles obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de roles', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado de todos los permisos disponibles en el sistema.
     *
     * Este método retorna todos los permisos registrados en el sistema con opciones
     * de búsqueda por nombre. Es útil para formularios de creación/edición de roles
     * donde se necesita mostrar la lista de permisos disponibles para asignar.
     *
     * @param ListRoleRequest $request La solicitud HTTP validada con parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de permisos
     *
     * @queryParam search string Término de búsqueda para filtrar permisos por nombre. Example: user
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de permisos obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "user.create",
     *       "guard_name": "web",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "user.edit",
     *       "guard_name": "web",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "search": ["El término de búsqueda debe ser una cadena de texto."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de permisos",
     *   "error": "Error message"
     * }
     */
    public function listPermisos(ListRoleRequest $request)
    {
        try {
            $query = Permission::query();

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->validated('search') . '%');
            }

            $permissions = $query->orderBy('name', 'asc')->get();

            return $this->successResponse($permissions, 'Listado de permisos obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de permisos', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado de roles con sus permisos asociados.
     *
     * Este método retorna todos los roles del sistema junto con los permisos
     * que tienen asignados. Es útil para mostrar la relación entre roles y
     * permisos en interfaces de administración o para generar reportes de
     * configuración de seguridad.
     *
     * @param ListRoleRequest $request La solicitud HTTP validada con parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con roles y sus permisos
     *
     * @queryParam search string Término de búsqueda para filtrar roles por nombre. Example: admin
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de roles con permisos obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Administrador",
     *       "guard_name": "web",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z",
     *       "permissions": [
     *         {
     *           "id": 1,
     *           "name": "user.create",
     *           "guard_name": "web"
     *         },
     *         {
     *           "id": 2,
     *           "name": "user.edit",
     *           "guard_name": "web"
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "search": ["El término de búsqueda debe ser una cadena de texto."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener roles con permisos",
     *   "error": "Error message"
     * }
     */
    public function listRolesPermisos(ListRoleRequest $request)
    {
        try {
            $query = Role::with('permissions');

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->validated('search') . '%');
            }

            $roles = $query->orderBy('name', 'asc')->get();

            return $this->successResponse($roles, 'Listado de roles con permisos obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener roles con permisos', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un listado de roles con sus permisos y conteo de usuarios asignados.
     *
     * Este método proporciona una vista completa de los roles del sistema,
     * incluyendo los permisos asociados y el número de usuarios que tienen
     * asignado cada rol. Es especialmente útil para dashboards de administración
     * y reportes de distribución de usuarios por roles.
     *
     * La consulta está optimizada para evitar el problema N+1, realizando
     * una sola consulta para obtener todos los conteos de usuarios.
     *
     * @param ListRoleRequest $request La solicitud HTTP validada con parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con roles, permisos y conteo de usuarios
     *
     * @queryParam search string Término de búsqueda para filtrar roles por nombre. Example: admin
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de roles con usuarios obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Administrador",
     *       "guard_name": "web",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z",
     *       "permissions": [
     *         {
     *           "id": 1,
     *           "name": "user.create",
     *           "guard_name": "web"
     *         }
     *       ],
     *       "users_count": 5
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "search": ["El término de búsqueda debe ser una cadena de texto."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener roles con usuarios",
     *   "error": "Error message"
     * }
     */
    public function rolesConUsuarios(ListRoleRequest $request)
    {
        try {
            $query = Role::with('permissions');

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->validated('search') . '%');
            }

            $roles = $query->orderBy('name', 'asc')->get();

            // Optimizar consulta de conteo de usuarios usando una sola consulta
            $roleIds = $roles->pluck('id');
            $userCounts = DB::table('model_has_roles')
                ->whereIn('role_id', $roleIds)
                ->where('model_type', User::class)
                ->select('role_id', DB::raw('count(*) as users_count'))
                ->groupBy('role_id')
                ->pluck('users_count', 'role_id');

            $rolesData = $roles->map(function ($role) use ($userCounts) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                    'permissions' => $role->permissions,
                    'users_count' => $userCounts->get($role->id, 0)
                ];
            });

            return $this->successResponse($rolesData, 'Listado de roles con usuarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener roles con usuarios', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo rol en el sistema.
     *
     * Este método permite crear un nuevo rol con un nombre único y asignarle
     * permisos específicos. El proceso se ejecuta dentro de una transacción
     * para garantizar la integridad de los datos.
     *
     * @param StoreRoleRequest $request La solicitud HTTP validada con los datos del rol a crear
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el rol creado
     *
     * @bodyParam name string required Nombre único del rol. Example: "Editor de Contenido"
     * @bodyParam permissions array required Array de nombres de permisos a asignar. Example: ["user.read", "content.edit"]
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Rol creado exitosamente",
     *   "data": {
     *     "id": 2,
     *     "name": "Editor de Contenido",
     *     "guard_name": "web",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "permissions": [
     *       {
     *         "id": 1,
     *         "name": "user.read",
     *         "guard_name": "web"
     *       },
     *       {
     *         "id": 2,
     *         "name": "content.edit",
     *         "guard_name": "web"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "name": ["El nombre del rol ya se encuentra registrado."],
     *     "permissions": ["Debe asignar al menos un permiso al rol."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el rol",
     *   "error": "Error message"
     * }
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            DB::beginTransaction();

            $role = Role::create(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions'));

            DB::commit();

            return $this->successResponse(
                $role->load('permissions'),
                'Rol creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el rol', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un rol específico con sus permisos asociados.
     *
     * Este método permite obtener la información detallada de un rol específico,
     * incluyendo todos los permisos que tiene asignados. Es útil para mostrar
     * los detalles de un rol o para formularios de edición.
     *
     * @param string $id El ID del rol a obtener
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el rol y sus permisos
     *
     * @urlParam id integer required El ID del rol. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Rol encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "name": "Administrador",
     *     "guard_name": "web",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "permissions": [
     *       {
     *         "id": 1,
     *         "name": "user.create",
     *         "guard_name": "web"
     *       },
     *       {
     *         "id": 2,
     *         "name": "user.edit",
     *         "guard_name": "web"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Rol no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el rol",
     *   "error": "Error message"
     * }
     */
    public function show(string $id)
    {
        try {
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return $this->errorResponse('Rol no encontrado', null, 404);
            }

            return $this->successResponse($role, 'Rol encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el rol', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un rol existente en el sistema.
     *
     * Este método permite modificar el nombre y los permisos de un rol existente.
     * El proceso se ejecuta dentro de una transacción para garantizar la integridad
     * de los datos. La validación asegura que el nombre sea único, excluyendo
     * el rol actual de la validación.
     *
     * @param UpdateRoleRequest $request La solicitud HTTP validada con los datos actualizados del rol
     * @param Role $role El rol a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el rol actualizado
     *
     * @bodyParam name string required Nuevo nombre único del rol. Example: "Editor Senior"
     * @bodyParam permissions array required Array de nombres de permisos a asignar. Example: ["user.read", "content.edit", "content.delete"]
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Rol actualizado exitosamente",
     *   "data": {
     *     "id": 2,
     *     "name": "Editor Senior",
     *     "guard_name": "web",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "permissions": [
     *       {
     *         "id": 1,
     *         "name": "user.read",
     *         "guard_name": "web"
     *       },
     *       {
     *         "id": 2,
     *         "name": "content.edit",
     *         "guard_name": "web"
     *       },
     *       {
     *         "id": 3,
     *         "name": "content.delete",
     *         "guard_name": "web"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "name": ["El nombre del rol ya se encuentra registrado."],
     *     "permissions": ["Debe asignar al menos un permiso al rol."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el rol",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        try {
            DB::beginTransaction();

            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions'));

            DB::commit();

            return $this->successResponse(
                $role->load('permissions'),
                'Rol actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el rol', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un rol del sistema.
     *
     * Este método permite eliminar un rol del sistema, pero solo si no tiene
     * usuarios asignados. Esta validación previene la eliminación accidental
     * de roles que están en uso, lo que podría causar problemas de seguridad
     * o funcionalidad en el sistema.
     *
     * @param string $id El ID del rol a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del rol a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Rol eliminado exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Rol no encontrado"
     * }
     *
     * @response 409 {
     *   "status": false,
     *   "message": "No se puede eliminar el rol porque hay usuarios asignados a él",
     *   "error": {
     *     "users_count": 5
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el rol",
     *   "error": "Error message"
     * }
     */
    public function destroy(string $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return $this->errorResponse('Rol no encontrado', null, 404);
            }

            // Verificar si hay usuarios asignados al rol
            $usersCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', User::class)
                ->count();

            if ($usersCount > 0) {
                return $this->errorResponse(
                    'No se puede eliminar el rol porque hay usuarios asignados a él',
                    ['users_count' => $usersCount],
                    409
                );
            }

            $role->delete();

            return $this->successResponse(null, 'Rol eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el rol', $e->getMessage(), 500);
        }
    }
}
