<?php

namespace Tests\Feature\Calidad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature M07 Calidad — Organigrama.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
class OrganigramaAccessTest extends TestCase
{

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Calidad - Organigrama -> Listar',
            'Calidad - Organigrama -> Crear',
            'Calidad - Organigrama -> Editar',
            'Calidad - Organigrama -> Mostrar',
            'Calidad - Organigrama -> Eliminar',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Gestor Calidad']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $this->getJson('/api/calidad/organigrama')->assertStatus(401);
        $this->postJson('/api/calidad/organigrama', [])->assertStatus(401);
    }

    /** @test */
    public function usuario_con_permisos_puede_listar()
    {
        $this->actingAs($this->user)
            ->getJson('/api/calidad/organigrama')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_con_permisos_puede_ver_dependencias()
    {
        $this->actingAs($this->user)
            ->getJson('/api/calidad/organigrama/dependencias')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_con_permisos_puede_ver_estadisticas()
    {
        $this->actingAs($this->user)
            ->getJson('/api/calidad/organigrama/estadisticas')
            ->assertStatus(200);
    }
}
