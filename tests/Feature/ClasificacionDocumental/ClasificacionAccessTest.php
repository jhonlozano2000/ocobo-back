<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature Clasificación Documental — TRD, TVD, Versiones.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
class ClasificacionAccessTest extends TestCase
{

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'TRD -> Listar', 'TRD -> Mostrar', 'TRD -> Crear',
            'TRD -> Editar', 'TRD -> Eliminar', 'TRD -> Versiones',
            'TVD -> Listar', 'TVD -> Crear',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Gestor Clasificacion']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);
    }

    /** @test */
    public function requiere_autenticacion_trd()
    {
        $this->getJson('/api/clasifica-documental/trd')->assertStatus(401);
    }

    /** @test */
    public function requiere_autenticacion_tvd()
    {
        $this->getJson('/api/clasifica-documental/tvd')->assertStatus(401);
    }

    /** @test */
    public function usuario_con_permisos_puede_listar_trd()
    {
        $this->actingAs($this->user)
            ->getJson('/api/clasifica-documental/trd')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_con_permisos_puede_listar_tvd()
    {
        $this->actingAs($this->user)
            ->getJson('/api/clasifica-documental/tvd')
            ->assertStatus(200);
    }

    /** @test */
    public function usuario_con_permisos_puede_ver_estadisticas_trd()
    {
        $this->actingAs($this->user)
            ->getJson('/api/clasifica-documental/trd/estadisticas/totales')
            ->assertStatus(200);
    }
}
