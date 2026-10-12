<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JefeDeArchivoHabilidadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_usuario_con_rol_jefe_de_archivo_tiene_la_habilidad(): void
    {
        $rol = Role::firstOrCreate(['name' => 'Jefe de Archivo', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($rol);

        $this->assertTrue(Gate::forUser($user)->allows('Jefe de Archivo'));
        $this->assertTrue($user->can('Jefe de Archivo'));
    }

    public function test_usuario_sin_rol_ni_permiso_es_rechazado(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('Jefe de Archivo'));
        $this->assertFalse($user->can('Jefe de Archivo'));
    }

    public function test_usuario_administrador_tiene_la_habilidad(): void
    {
        $rolAdmin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($rolAdmin);

        $this->assertTrue(Gate::forUser($admin)->allows('Jefe de Archivo'));
        $this->assertTrue($admin->can('Jefe de Archivo'));
    }
}
