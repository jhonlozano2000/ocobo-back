<?php

namespace Tests\Feature\ControlAcceso;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature M06 Control de Acceso — Roles y Permisos.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
class RolesAccessTest extends TestCase
{

    protected User $user;
    protected User $sinPermisos;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Control de acceso - Roles -> Listar',
            'Control de acceso - Roles -> Crear',
            'Control de acceso - Roles -> Editar',
            'Control de acceso - Roles -> Mostrar',
            'Control de acceso - Roles -> Eliminar',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Admin Roles Test']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);

        $this->sinPermisos = User::factory()->create();
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $this->getJson('/api/control-acceso/roles')->assertStatus(401);
        $this->postJson('/api/control-acceso/roles', [])->assertStatus(401);
    }

    /** @test */
    public function sin_permiso_roles_no_puede_listar()
    {
        // Usuario autenticado pero SIN permisos de roles
        $this->actingAs($this->sinPermisos)
            ->getJson('/api/control-acceso/roles')
            ->assertStatus(403);
    }

    /** @test */
    public function con_permiso_puede_listar_roles()
    {
        $this->actingAs($this->user)
            ->getJson('/api/control-acceso/roles')
            ->assertStatus(200);
    }

    /** @test */
    public function con_permiso_puede_acceder_roles_y_permisos()
    {
        $this->actingAs($this->user)
            ->getJson('/api/control-acceso/roles-y-permisos')
            ->assertStatus(200);
    }
}
