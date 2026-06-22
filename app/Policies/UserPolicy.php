<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determina si el usuario puede ver la lista de usuarios.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Ver');
    }

    /**
     * Determina si el usuario puede ver un usuario específico.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Ver');
    }

    /**
     * Determina si el usuario puede crear usuarios.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Crear');
    }

    /**
     * Determina si el usuario puede actualizar un usuario.
     */
    public function update(User $user, User $model): bool
    {
        // Un usuario no puede modificarse a sí mismo con permisos elevados
        if ($user->id === $model->id) {
            // Usuario puede actualizar su propio perfil pero no roles
            return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Editar');
        }

        // Verificar si intenta escalar privilegios
        if ($this->intentaEscalarPrivilegios($user, $model)) {
            return false;
        }

        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Editar');
    }

    /**
     * Determina si el usuario puede eliminar un usuario.
     */
    public function delete(User $user, User $model): bool
    {
        // No puede eliminarse a sí mismo
        if ($user->id === $model->id) {
            return false;
        }

        // No puede eliminar usuarios con rol de Administrador
        if ($model->hasRole('Administrador')) {
            return false;
        }

        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Eliminar');
    }

    /**
     * Determina si el usuario puede gestionar roles de un usuario.
     */
    public function manageRoles(User $user, User $model): bool
    {
        // No puede modificarse roles a sí mismo
        if ($user->id === $model->id) {
            return false;
        }

        // No puede modificar roles de Administrador
        if ($model->hasRole('Administrador')) {
            return false;
        }

        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Roles -> Asignar');
    }

    /**
     * Determina si el usuario puede ver sesiones activas.
     */
    public function viewSessions(User $user): bool
    {
        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Ver Sesiones');
    }

    /**
     * Determina si el usuario puede forzar logout de un usuario.
     */
    public function forceLogout(User $user, User $model): bool
    {
        // No puede forzarse logout a sí mismo
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermissionTo('Gestionar -> Control Acceso -> Usuarios -> Ver Sesiones');
    }

    /**
     * Verifica si un usuario está intentando escalar privilegios sobre otro.
     */
    private function intentaEscalarPrivilegios(User $user, User $model): bool
    {
        // Verificar si el usuario que se está modificando tiene rol de Administrador
        // y el usuario actual no lo tiene
        if ($model->hasRole('Administrador') && ! $user->hasRole('Administrador')) {
            return true;
        }

        return false;
    }
}
