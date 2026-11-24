<?php

namespace App\Services\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    private const PERMISSION_GUARD = 'web';

    /**
     * Obtiene roles con filtros.
     */
    public function getAll(array $filters = [])
    {
        $query = Role::with('permissions');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->orderBy('name', 'asc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Obtiene roles con conteo de usuarios.
     */
    public function getWithUsers(): Collection
    {
        $roles = Role::with('permissions')->orderBy('name', 'asc')->get();

        $roleIds = $roles->pluck('id');
        $userCounts = DB::table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->where('model_type', User::class)
            ->select('role_id', DB::raw('count(*) as users_count'))
            ->groupBy('role_id')
            ->pluck('users_count', 'role_id');

        return $roles->map(fn ($r) => array_merge($r->toArray(), [
            'users_count' => $userCounts->get($r->id, 0),
        ]));
    }

    /**
     * Obtiene todos los permisos.
     */
    public function getAllPermissions(array $filters = [])
    {
        // El selector solo debe ofrecer permisos compatibles con los roles de la aplicación.
        $query = Permission::query()->where('guard_name', self::PERMISSION_GUARD);

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->orderBy('name', 'asc')->get();
    }

    /**
     * Obtiene estadísticas.
     */
    public function getStats(): array
    {
        $totalRoles = Role::count();
        $totalPermisos = Permission::count();
        $totalUsuarios = User::count();

        $usuariosConRoles = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->distinct('model_id')
            ->count('model_id');

        return [
            'total_roles' => $totalRoles,
            'total_permisos' => $totalPermisos,
            'total_usuarios' => $totalUsuarios,
            'usuarios_con_roles' => $usuariosConRoles,
            'usuarios_sin_roles' => $totalUsuarios - $usuariosConRoles,
        ];
    }

    /**
     * Obtiene todos los roles con sus permisos.
     */
    public function getAllWithPermissions(): Collection
    {
        return Role::with('permissions')->orderBy('name', 'asc')->get();
    }

    /**
     * Crea un nuevo rol.
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create(['name' => $data['name'], 'guard_name' => self::PERMISSION_GUARD]);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role->fresh(['permissions']);
        });
    }

    /**
     * Actualiza un rol.
     */
    public function update(int $id, array $data): ?Role
    {
        return DB::transaction(function () use ($id, $data): ?Role {
            $role = Role::find($id);

            if (! $role) {
                return null;
            }

            // Los roles vacíos creados antes del fix heredaron la guarda Sanctum de la petición.
            // Solo se pueden convertir si no tienen asignaciones que cambien de significado.
            if ($role->guard_name !== self::PERMISSION_GUARD) {
                $asignado = DB::table('model_has_roles')->where('role_id', $role->id)->exists();

                if ($asignado || $role->permissions()->exists()) {
                    throw ValidationException::withMessages([
                        'name' => 'El rol utiliza una guarda incompatible y tiene asignaciones. Revise sus usuarios y permisos antes de editarlo.',
                    ]);
                }

                $role->guard_name = self::PERMISSION_GUARD;
            }

            $role->name = $data['name'];
            $role->save();
            $role->syncPermissions($data['permissions'] ?? []);

            return $role->fresh(['permissions']);
        });
    }

    /**
     * Elimina un rol si no tiene usuarios.
     */
    public function delete(int $id): array
    {
        $role = Role::find($id);

        if (! $role) {
            return ['success' => false, 'message' => 'Rol no encontrado', 'code' => 404];
        }

        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->count();

        if ($usersCount > 0) {
            return ['success' => false, 'message' => 'No se puede eliminar el rol porque hay usuarios asignados', 'code' => 409, 'users_count' => $usersCount];
        }

        $role->delete();

        return ['success' => true];
    }
}
