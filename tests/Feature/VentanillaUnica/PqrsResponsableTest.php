<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsResponsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PqrsResponsableTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $user2;
    protected UserCargo $userCargo;
    protected UserCargo $userCargo2;
    protected VentanillaPqrs $pqrs;

    protected function setUp(): void
    {
        parent::setUp();

        $permisos = [
            'Radicar -> PQRSF -> Listar',
            'Radicar -> PQRSF -> Crear',
            'Radicar -> PQRSF -> Editar',
            'Radicar -> PQRSF -> Eliminar',
            'Radicar -> PQRSF -> Comentar',
            'Radicar -> PQRSF -> Comentar Crear',
            'Radicar -> PQRSF -> Comentar Editar',
            'Radicar -> PQRSF -> Comentar Eliminar',
            'Radicar -> PQRSF -> Pases',
            'Radicar -> PQRSF -> Compartir',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $role = Role::firstOrCreate(['name' => 'Ventanilla']);
        $role->givePermissionTo($permisos);

        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dirección General',
            'cod_organico' => 'DIR-001',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista PQRS',
            'cod_organico' => 'CPQRS-001',
            'parent' => $dependencia->id,
        ]);

        $cargo2 = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Jefe PQRS',
            'cod_organico' => 'CPQRS-002',
            'parent' => $dependencia->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->user2 = User::factory()->create();
        $this->user2->assignRole($role);

        $this->userCargo = UserCargo::create([
            'user_id' => $this->user->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        $this->userCargo2 = UserCargo::create([
            'user_id' => $this->user2->id,
            'cargo_id' => $cargo2->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        $lista = ConfigLista::create(['cod' => 'TipoPQRSF', 'nombre' => 'Tipo PQRS']);
        $tipoPqrs = ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'PQR', 'nombre' => 'PQRS']);

        $this->pqrs = VentanillaPqrs::create([
            'tipo_pqrs_id' => $tipoPqrs->id,
            'prioridad' => 'Normal',
            'estado_tramite' => 'Pendiente',
            'asunto' => 'PQRS de prueba para responsables',
            'detalle_solicitud' => 'Detalle de prueba',
            'usuario_crea' => $this->user->id,
            'clasificacion_documental_trd_id' => null,
            'gestion_tercero_id' => null,
            'config_divi_poli_id_afectado' => null,
            'fechor_tramite' => now(),
            'fecha_vencimiento' => now()->addDays(15),
        ]);
    }

    /** @test */
    public function puede_listar_responsables_de_un_pqrs()
    {
        VentanillaPqrsResponsable::create([
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => true,
        ]);

        VentanillaPqrsResponsable::create([
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo2->id,
            'custodio' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/pqrs-responsables?pqrs_id={$this->pqrs->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function puede_asignar_responsable_a_un_pqrs()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/responsables", [
                'responsables' => [
                    [
                        'users_cargos_id' => $this->userCargo->id,
                        'custodio' => true,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('ventanilla_pqrs_responsables', 1);
        $this->assertDatabaseHas('ventanilla_pqrs_responsables', [
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => true,
        ]);
    }

    /** @test */
    public function puede_actualizar_responsable_cambiar_custodio()
    {
        $responsable = VentanillaPqrsResponsable::create([
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/ventanilla/pqrs-responsables/{$responsable->id}", [
                'custodio' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.custodio', true);

        $this->assertDatabaseHas('ventanilla_pqrs_responsables', [
            'id' => $responsable->id,
            'custodio' => true,
        ]);
    }

    /** @test */
    public function puede_marcar_responsable_como_visto()
    {
        $responsable = VentanillaPqrsResponsable::create([
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs-responsables/{$responsable->id}/marcar-visto");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $responsable->refresh();
        $this->assertNotNull($responsable->fechor_visto);
    }

    /** @test */
    public function puede_eliminar_responsable()
    {
        $responsable = VentanillaPqrsResponsable::create([
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/ventanilla/pqrs-responsables/{$responsable->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('ventanilla_pqrs_responsables', 0);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $response = $this->getJson('/api/ventanilla/pqrs-responsables');
        $response->assertStatus(401);

        $response = $this->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/responsables", []);
        $response->assertStatus(401);
    }
}
