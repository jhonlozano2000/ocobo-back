<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlAccesoUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear rol de administrador si no existe
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        
        // Crear usuario admin para pruebas
        $this->user = User::factory()->create([
            'estado' => 1,
        ]);
        
        // Asignar rol de administrador
        $this->user->assignRole('Administrador');
    }

    /** @test */
    public function usuarios_index_retorna_lista_de_usuarios()
    {
        // Crear usuarios adicionales
        User::factory()->count(5)->create(['estado' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/control-acceso/users');

        // Debug: ver el error si falla
        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function usuarios_index_con_filtro_search()
    {
        // Crear usuarios con nombres específicos
        User::factory()->create([
            'nombres' => 'Usuario de Prueba',
            'estado' => 1
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/control-acceso/users?search=Prueba');

        $response->assertStatus(200);
    }

    /** @test */
    public function usuarios_estadisticas_retorna_datos()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/control-acceso/users/estadisticas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'total_users',
                    'total_users_activos',
                    'total_users_inactivos'
                ]
            ]);
    }

    /** @test */
    public function usuarios_sin_autenticacion_retorna_401()
    {
        $response = $this->getJson('/api/control-acceso/users');

        $response->assertStatus(401);
    }
}