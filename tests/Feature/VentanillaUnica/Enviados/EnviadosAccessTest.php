<?php

namespace Tests\Feature\VentanillaUnica\Enviados;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature M02 Radicación Enviada — Acceso y autorización.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
class EnviadosAccessTest extends TestCase
{

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Radicar -> Cores. Enviada -> Listar',
            'Radicar -> Cores. Enviada -> Crear',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Radicador Enviados Test']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $this->getJson('/api/ventanilla/radica-enviada')->assertStatus(401);
        $this->postJson('/api/ventanilla/radica-enviada', [])->assertStatus(401);
    }

    /** @test */
    public function usuario_con_permisos_puede_listar()
    {
        $this->actingAs($this->user)
            ->getJson('/api/ventanilla/radica-enviada')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_con_permisos_puede_ver_estadisticas()
    {
        $this->actingAs($this->user)
            ->getJson('/api/ventanilla/radica-enviada/estadisticas')
            ->assertStatus(200);
    }
}
