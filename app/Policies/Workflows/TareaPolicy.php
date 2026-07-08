<?php

declare(strict_types=1);

namespace App\Policies\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use Illuminate\Auth\Access\HandlesAuthorization;

class TareaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Workflows -> Tareas -> Listar');
    }

    public function view(User $user, Tarea $tarea): bool
    {
        return $user->hasPermissionTo('Workflows -> Tareas -> Mostrar')
            && ($tarea->propietarios()->where('user_id', $user->id)->exists()
                || $tarea->responsables()->where('user_id', $user->id)->exists()
                || $user->hasRole('Administrador'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Workflows -> Tareas -> Crear');
    }

    public function update(User $user, Tarea $tarea): bool
    {
        return $user->hasPermissionTo('Workflows -> Tareas -> Editar')
            && ($tarea->propietarios()->where('user_id', $user->id)->exists()
                || $user->hasRole('Administrador'));
    }

    public function delete(User $user, Tarea $tarea): bool
    {
        return $user->hasPermissionTo('Workflows -> Tareas -> Eliminar')
            && ($tarea->propietarios()->where('user_id', $user->id)->exists()
                || $user->hasRole('Administrador'));
    }
}
