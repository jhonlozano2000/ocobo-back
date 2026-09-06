<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * M05 TVD — el store/update originales pasaban $request->all() al modelo sin
 * validación de servidor. Estos tests cubren el FormRequest y la jerarquía.
 */
class TVDValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CalidadOrganigrama $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = ['TVD -> Listar', 'TVD -> Crear', 'TVD -> Editar'];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Gestor TVD']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);

        $this->dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dependencia TVD Test',
            'cod_organico' => 'TVD'.random_int(100000, 999999),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'SerieDocumental',
            'cod' => 'SD'.random_int(1000, 9999),
            'nom' => 'Serie documental de prueba',
            'dependencia_id' => $this->dependencia->id,
            'gestion' => 5,
            'central' => 3,
            'total_anios' => 8,
            'estado' => true,
        ], $overrides);
    }

    public function test_crea_serie_documental_valida(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/clasifica-documental/tvd', $this->payload());

        $response->assertStatus(201);
        $this->assertDatabaseCount('clasificacion_documental_tvd', 1);
    }

    public function test_rechaza_tipo_inventado(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', $this->payload(['tipo' => 'OtraCosa']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo');
    }

    public function test_rechaza_dependencia_inexistente(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', $this->payload(['dependencia_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dependencia_id');
    }

    public function test_rechaza_codigo_repetido_en_la_misma_dependencia(): void
    {
        $payload = $this->payload(['cod' => 'SD-REP']);

        $this->actingAs($this->user)->postJson('/api/clasifica-documental/tvd', $payload)->assertStatus(201);

        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', array_merge($payload, ['nom' => 'Segunda']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod');
    }

    public function test_subserie_exige_padre(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', $this->payload([
                'tipo' => 'SubSerieDocumental',
                'cod' => 'SS-SIN-PADRE',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent');
    }

    public function test_subserie_con_serie_padre_valida_se_crea(): void
    {
        $serie = ClasificacionDocumentalTVD::create($this->payload(['cod' => 'SD-01']) + ['user_register' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', $this->payload([
                'tipo' => 'SubSerieDocumental',
                'cod' => 'SS-01',
                'nom' => 'Subserie de prueba',
                'parent' => $serie->id,
            ]))
            ->assertStatus(201);
    }

    public function test_serie_no_puede_tener_padre(): void
    {
        $serie = ClasificacionDocumentalTVD::create($this->payload(['cod' => 'SD-02']) + ['user_register' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', $this->payload([
                'cod' => 'SD-03',
                'parent' => $serie->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent');
    }

    public function test_no_acepta_campos_no_rellenable(): void
    {
        // Antes el servicio creaba con $request->all(); un id forjado debía ser ignorado.
        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd', $this->payload(['id' => 55555]))
            ->assertStatus(201);

        $this->assertDatabaseMissing('clasificacion_documental_tvd', ['id' => 55555]);
    }

    public function test_update_rechaza_autoparentalidad(): void
    {
        $serie = ClasificacionDocumentalTVD::create($this->payload(['cod' => 'SD-UP']) + ['user_register' => $this->user->id]);

        $this->actingAs($this->user)
            ->putJson("/api/clasifica-documental/tvd/{$serie->id}", ['parent' => $serie->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent');
    }

    public function test_update_valido_cambia_nombre(): void
    {
        $serie = ClasificacionDocumentalTVD::create($this->payload(['cod' => 'SD-OK']) + ['user_register' => $this->user->id]);

        $this->actingAs($this->user)
            ->putJson("/api/clasifica-documental/tvd/{$serie->id}", ['nom' => 'Nombre corregido'])
            ->assertOk();

        $this->assertDatabaseHas('clasificacion_documental_tvd', [
            'id' => $serie->id,
            'nom' => 'Nombre corregido',
        ]);
    }

    public function test_lista_devuelve_arbol_raiz(): void
    {
        ClasificacionDocumentalTVD::create($this->payload(['cod' => 'SD-L1']) + ['user_register' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/clasifica-documental/tvd');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
