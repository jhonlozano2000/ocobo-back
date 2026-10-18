<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use Database\Seeders\Permisos\PermisosWorkflowsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M19-11: además de Administrador, el seeder deja dos perfiles operativos del módulo.
 */
class PerfilesWorkflowsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_el_seeder_crea_disenador_y_ejecutor_de_forma_idempotente(): void
    {
        $this->seed(PermisosWorkflowsSeeder::class);
        $this->seed(PermisosWorkflowsSeeder::class);

        $disenador = Role::findByName(PermisosWorkflowsSeeder::ROL_DISENADOR, 'web');
        $ejecutor = Role::findByName(PermisosWorkflowsSeeder::ROL_EJECUTOR, 'web');

        $this->assertCount(17, $disenador->permissions);
        $this->assertEqualsCanonicalizing(
            PermisosWorkflowsSeeder::PERMISOS_EJECUTOR,
            $ejecutor->permissions->pluck('name')->all()
        );
        $this->assertFalse($ejecutor->hasPermissionTo('Workflows -> Workflows -> Editar'));
        $this->assertFalse($ejecutor->hasPermissionTo('Workflows -> Tareas -> Asignar'));
    }

    public function test_el_ejecutor_ve_sus_tareas_pero_no_disena_flujos(): void
    {
        $this->seed(PermisosWorkflowsSeeder::class);
        $usuario = User::factory()->create(['estado' => 1]);
        $usuario->assignRole(PermisosWorkflowsSeeder::ROL_EJECUTOR);

        $this->actingAs($usuario)->getJson('/api/workflows/mis-tareas')->assertOk();
        $this->actingAs($usuario)->getJson('/api/workflows/workflows')->assertOk();
        $this->actingAs($usuario)->postJson('/api/workflows/workflows', ['nombre' => 'No'])->assertForbidden();
    }
}
