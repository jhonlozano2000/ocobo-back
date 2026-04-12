<?php

use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigDiviPoliTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar seeders básicos para tener usuarios
        $this->seed([
            \Database\Seeders\ControlAcceso\RoleSeeder::class,
            \Database\Seeders\ControlAcceso\UsersSeeder::class,
        ]);
        
        // Usar el usuario administrador del seeder
        $this->user = User::where('email', 'admin@admin.com')->first();
    }

    public function test_list_division_politica(): void
    {
        ConfigDiviPoli::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/config/division-politica');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => ['id', 'nombre', 'tipo', 'codigo', 'estado']
                    ]
                ]
            ]);
    }

    public function test_create_division_politica(): void
    {
        $data = [
            'nombre' => 'Colombia',
            'tipo' => 'Pais',
            'codigo' => 'CO',
            'estado' => 1,
            'divi_poli_id' => null
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/config/division-politica', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Colombia')
            ->assertJsonPath('data.tipo', 'Pais');

        $this->assertDatabaseHas('config_divi_polis', [
            'nombre' => 'Colombia',
            'tipo' => 'Pais'
        ]);
    }

    public function test_create_division_politica_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/config/division-politica', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'tipo']);
    }

    public function test_show_division_politica(): void
    {
        $diviPoli = ConfigDiviPoli::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/config/division-politica/{$diviPoli->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $diviPoli->id)
            ->assertJsonPath('data.nombre', $diviPoli->nombre);
    }

    public function test_update_division_politica(): void
    {
        $diviPoli = ConfigDiviPoli::factory()->create(['nombre' => 'Antiguo']);

        $response = $this->actingAs($this->user)
            ->putJson("/api/config/division-politica/{$diviPoli->id}", [
                'nombre' => 'Nuevo Nombre'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Nuevo Nombre');

        $this->assertDatabaseHas('config_divi_polis', [
            'id' => $diviPoli->id,
            'nombre' => 'Nuevo Nombre'
        ]);
    }

    public function test_delete_division_politica(): void
    {
        $diviPoli = ConfigDiviPoli::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/config/division-politica/{$diviPoli->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('config_divi_polis', [
            'id' => $diviPoli->id
        ]);
    }

    public function test_list_division_politica_by_type(): void
    {
        ConfigDiviPoli::factory()->create(['tipo' => 'Pais', 'nombre' => 'Colombia']);
        ConfigDiviPoli::factory()->create(['tipo' => 'Pais', 'nombre' => 'Venezuela']);
        ConfigDiviPoli::factory()->create(['tipo' => 'Departamento', 'nombre' => 'Cundinamarca']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/config/division-politica/list/por-tipo/Pais');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_estadisticas_division_politica(): void
    {
        ConfigDiviPoli::factory()->count(3)->create(['tipo' => 'Pais', 'estado' => 1]);
        ConfigDiviPoli::factory()->count(2)->create(['tipo' => 'Pais', 'estado' => 0]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/config/division-politica/estadisticas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'total',
                    'activos',
                    'inactivos',
                    'por_tipo'
                ]
            ]);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/config/division-politica');

        $response->assertStatus(401);
    }
}