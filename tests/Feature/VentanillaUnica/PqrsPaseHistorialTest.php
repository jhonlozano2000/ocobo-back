<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsPaseHistorial;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsResponsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PqrsPaseHistorialTest extends TestCase
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
            'Radicar -> PQRSF -> Mostrar',
            'Radicar -> PQRSF -> Pases',
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
            'asunto' => 'PQRS de prueba para pases',
            'detalle_solicitud' => 'Detalle de prueba',
            'usuario_crea' => $this->user->id,
            'clasificacion_documental_trd_id' => null,
            'gestion_tercero_id' => null,
            'config_divi_poli_id_afectado' => null,
            'fechor_tramite' => now(),
            'fecha_vencimiento' => now()->addDays(15),
        ]);

        VentanillaPqrsResponsable::create([
            'pqrs_id' => $this->pqrs->id,
            'users_cargos_id' => $this->userCargo->id,
            'custodio' => true,
        ]);
    }

    /** @test */
    public function puede_crear_pase()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo2->id,
                'usuario_destino_id' => $this->user2->id,
                'tipo' => VentanillaPqrsPaseHistorial::TIPO_PASE,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.historial.tipo', VentanillaPqrsPaseHistorial::TIPO_PASE)
            ->assertJsonPath('data.historial.usuario_origen_id', $this->user->id)
            ->assertJsonPath('data.historial.users_cargos_destino_id', $this->userCargo2->id)
            ->assertJsonPath('data.historial.usuario_destino_id', $this->user2->id);

        $this->assertDatabaseCount('ventanilla_pqrs_pase_historial', 1);
        $this->assertDatabaseHas('ventanilla_pqrs_pase_historial', [
            'pqrs_id' => $this->pqrs->id,
            'tipo' => VentanillaPqrsPaseHistorial::TIPO_PASE,
            'usuario_origen_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function puede_crear_asignacion_inicial()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo2->id,
                'usuario_destino_id' => $this->user2->id,
                'tipo' => VentanillaPqrsPaseHistorial::TIPO_ASIGNACION_INICIAL,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.historial.tipo', VentanillaPqrsPaseHistorial::TIPO_ASIGNACION_INICIAL);
    }

    /** @test */
    public function puede_crear_reasignacion()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo2->id,
                'usuario_destino_id' => $this->user2->id,
                'tipo' => VentanillaPqrsPaseHistorial::TIPO_REASIGNACION,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.historial.tipo', VentanillaPqrsPaseHistorial::TIPO_REASIGNACION);
    }

    /** @test */
    public function falla_si_tipo_pase_invalido()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo2->id,
                'usuario_destino_id' => $this->user2->id,
                'tipo' => 'tipo_invalido',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    /** @test */
    public function falla_si_falta_cargo_destino()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", [
                'usuario_destino_id' => $this->user2->id,
                'tipo' => VentanillaPqrsPaseHistorial::TIPO_PASE,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['users_cargos_destino_id']);
    }

    /** @test */
    public function puede_listar_historial_pases_de_un_pqrs()
    {
        VentanillaPqrsPaseHistorial::create([
            'pqrs_id' => $this->pqrs->id,
            'usuario_origen_id' => $this->user->id,
            'users_cargos_destino_id' => $this->userCargo2->id,
            'usuario_destino_id' => $this->user2->id,
            'tipo' => VentanillaPqrsPaseHistorial::TIPO_PASE,
        ]);

        VentanillaPqrsPaseHistorial::create([
            'pqrs_id' => $this->pqrs->id,
            'usuario_origen_id' => $this->user2->id,
            'users_cargos_destino_id' => $this->userCargo->id,
            'usuario_destino_id' => $this->user->id,
            'tipo' => VentanillaPqrsPaseHistorial::TIPO_REASIGNACION,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function usuario_origen_se_asigna_automaticamente()
    {
        $response = $this->actingAs($this->user2)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo->id,
                'usuario_destino_id' => $this->user->id,
                'tipo' => VentanillaPqrsPaseHistorial::TIPO_PASE,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.historial.usuario_origen_id', $this->user2->id);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $response = $this->getJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase");
        $response->assertStatus(401);

        $response = $this->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/pase", []);
        $response->assertStatus(401);
    }

    /** @test */
    public function pqrs_inexistente_retorna_404()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/99999/pase", [
                'users_cargos_destino_id' => $this->userCargo2->id,
                'usuario_destino_id' => $this->user2->id,
                'tipo' => VentanillaPqrsPaseHistorial::TIPO_PASE,
            ]);

        $response->assertStatus(404);
    }
}
