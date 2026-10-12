<?php

namespace Tests\Feature\Calidad;

use Database\Seeders\Permisos\PermisosCalidadSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * M17-03: el rol "Radicador PQRSF" solo consulta el organigrama; el seeder revoca lo asignado de más.
 */
class PermisosCalidadSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_radicador_pqrsf_solo_consulta_el_organigrama(): void
    {
        $rol = Role::findOrCreate('Radicador PQRSF', 'web');
        // Estado heredado de versiones anteriores del seeder
        $rol->givePermissionTo(Permission::findOrCreate('Calidad - Organigrama -> Eliminar', 'web'));

        $this->seed(PermisosCalidadSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = $rol->fresh()->permissions->pluck('name')->filter(fn ($p) => str_starts_with($p, 'Calidad'));
        $this->assertEqualsCanonicalizing(
            ['Calidad - Organigrama -> Listar', 'Calidad - Organigrama -> Mostrar'],
            $permisos->values()->all()
        );
    }

    public function test_permisos_obsoletos_de_calidad_se_eliminan(): void
    {
        Permission::findOrCreate('Calidad -> Crear procesos', 'web');
        Permission::findOrCreate('Calidad -> Crear Macro Procesos', 'web');

        $this->seed(PermisosCalidadSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDatabaseMissing('permissions', ['name' => 'Calidad -> Crear procesos']);
        $this->assertDatabaseMissing('permissions', ['name' => 'Calidad -> Crear Macro Procesos']);
        $this->assertDatabaseHas('permissions', ['name' => 'Calidad - Organigrama -> Listar']);
    }
}
