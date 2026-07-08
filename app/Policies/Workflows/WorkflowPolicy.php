<?php

declare(strict_types=1);

namespace App\Policies\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Workflows -> Workflows -> Listar');
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('Workflows -> Workflows -> Mostrar')
            && ($workflow->creador_user_id === $user->id || $user->hasRole('Administrador'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Workflows -> Workflows -> Crear');
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('Workflows -> Workflows -> Editar')
            && ($workflow->creador_user_id === $user->id || $user->hasRole('Administrador'));
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('Workflows -> Workflows -> Eliminar')
            && ($workflow->creador_user_id === $user->id || $user->hasRole('Administrador'));
    }

    public function ejecutar(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('Workflows -> Instancias -> Ejecutar');
    }
}
