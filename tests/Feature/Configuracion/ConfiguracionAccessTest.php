<?php

namespace Tests\Feature\Configuracion;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature M08 Configuración — acceso y autorización.
 *
 * El módulo tiene permisos sembrados ('Config - ...') pero las rutas
 * solo exigen auth:sanctum. Esta suite valida que:
 * - Sin auth → 401
 * - Con auth → endpoints de lectura responden 200
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-24
 */
class ConfiguracionAccessTest extends TestCase
{

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Config - División política -> Listar',
            'Config - Listas -> Listar',
            'Config - Sedes -> Listar',
            'Config - Servidor de almacenamiento -> Listar',
            'Config - Otras configuraciones -> Listar',
            'Config - Calendario -> Listar',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Configurador']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);
    }

    /** @test */
    public function requiere_autenticacion_para_lecturas()
    {
        $this->getJson('/api/config/listas-cabeza')->assertStatus(401);
        $this->getJson('/api/config/division-politica')->assertStatus(401);
        $this->getJson('/api/config/sedes')->assertStatus(401);
        $this->getJson('/api/config/calendario-festivos')->assertStatus(401);
        $this->getJson('/api/config/servidores-archivos')->assertStatus(401);
        $this->getJson('/api/config/config-varias')->assertStatus(401);
    }

    /** @test */
    public function usuario_autenticado_puede_listar_division_politica()
    {
        $this->actingAs($this->user)
            ->getJson('/api/config/division-politica')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_autenticado_puede_listar_listas()
    {
        $this->actingAs($this->user)
            ->getJson('/api/config/listas-cabeza')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_autenticado_puede_listar_sedes()
    {
        $this->actingAs($this->user)
            ->getJson('/api/config/sedes')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_autenticado_puede_listar_servidores()
    {
        $this->actingAs($this->user)
            ->getJson('/api/config/servidores-archivos')
            ->assertStatus(200);
    }
}
