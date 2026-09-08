<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature del módulo Radicados Internos - Responsables.
 *
 * Cubre: listado por radicado, asignación, acuse digital con ownership,
 * eliminación y autenticación.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-23
 */
class RadicaInternoResponsableTest extends TestCase
{

    protected User $user;
    protected User $user2;
    protected UserCargo $userCargo;
    protected VentanillaRadicaInterno $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Radicar -> Cores. Interna -> Listar',
            'Radicar -> Cores. Interna -> Editar',
            'Radicar -> Cores. Interna -> Mostrar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $role = Role::firstOrCreate(['name' => 'VentanillaInterno']);
        $role->givePermissionTo($permisos);

        // FK paramétrica requerida por el radicado interno
        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dirección General',
            'cod_organico' => 'DIRGEN',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista Internos',
            'cod_organico' => 'CINTE001',
            'parent' => $dependencia->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->user2 = User::factory()->create();
        $this->user2->assignRole($role);

        $trd = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-INT-01',
            'nom' => 'Correspondencia Interna Test',
            'user_register' => $this->user->id,
        ]);

        $this->userCargo = UserCargo::create([
            'user_id' => $this->user->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        $this->radicado = VentanillaRadicaInterno::create([
            'num_radicado' => 'RAD-INT-'.uniqid(),
            'clasifica_documen_id' => $trd->id,
            'usuario_crea' => $this->user->id,
            'fec_docu' => now()->toDateString(),
            'fec_venci' => now()->addDays(10)->toDateString(),
            'asunto' => 'Radicado interno de prueba',
        ]);
    }

    /** @test */
    public function puede_listar_responsables_de_un_radicado()
    {
        VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $this->radicado->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => true,
            'fechor_visto' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/radica-interno/{$this->radicado->id}/responsables");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function puede_asignar_responsables_a_un_radicado()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/radica-interno/{$this->radicado->id}/responsables", [
                'responsables' => [
                    [
                        'users_cargos_id' => $this->userCargo->id,
                        'custodio' => true,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('ventanilla_radica_internos_responsa', 1);
    }

    /** @test */
    public function puede_marcar_como_visto_el_propio_responsable()
    {
        $responsable = VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $this->radicado->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/responsables-internos/{$responsable->id}/marcar-visto");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $responsable->refresh();
        $this->assertNotNull($responsable->fechor_visto);
    }

    /** @test */
    public function no_puede_marcar_visto_otro_usuario()
    {
        $responsable = VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $this->radicado->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => false,
        ]);

        // user2 tiene los mismos permisos pero NO es el responsable asignado
        $response = $this->actingAs($this->user2)
            ->postJson("/api/ventanilla/responsables-internos/{$responsable->id}/marcar-visto");

        $response->assertStatus(403);

        $responsable->refresh();
        $this->assertNull($responsable->fechor_visto);
    }

    /** @test */
    public function puede_eliminar_responsable()
    {
        $responsable = VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $this->radicado->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/ventanilla/responsables-internos/{$responsable->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($responsable);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $response = $this->getJson("/api/ventanilla/radica-interno/{$this->radicado->id}/responsables");
        $response->assertStatus(401);

        $response = $this->postJson("/api/ventanilla/radica-interno/{$this->radicado->id}/responsables", []);
        $response->assertStatus(401);
    }
}


