<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-23: tareas y flujos se asignaban a usuarios inactivos (nadie las atendía).
 */
class AsignarUsuarioInactivoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_no_se_asignan_usuarios_inactivos(): void
    {
        foreach (['Workflows -> Workflows -> Crear', 'Workflows -> Tareas -> Crear'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $usuario = User::factory()->create(['estado' => 1]);
        $usuario->givePermissionTo('Workflows -> Workflows -> Crear', 'Workflows -> Tareas -> Crear');
        $inactivo = User::factory()->create(['estado' => 0]);
        $workflow = Workflow::create(['nombre' => 'M19-23', 'estado' => 'activo', 'creador_user_id' => $usuario->id]);

        $this->actingAs($usuario)->postJson('/api/workflows/workflows', ['nombre' => 'M19-23 nuevo', 'administrador_user_id' => $inactivo->id])
            ->assertStatus(422)->assertJsonValidationErrors('administrador_user_id');

        $this->actingAs($usuario)->postJson('/api/workflows/tareas', [
            'workflow_id' => $workflow->id,
            'nombre' => 'Revisar',
            'responsables' => [$inactivo->id],
        ])->assertStatus(422)->assertJsonValidationErrors('responsables.0');

        $this->actingAs($usuario)->postJson('/api/workflows/tareas', [
            'workflow_id' => $workflow->id,
            'nombre' => 'Revisar',
            'responsables' => [$usuario->id],
        ])->assertSuccessful();
    }
}
