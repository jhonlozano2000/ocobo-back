<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature del módulo Radicados Internos - Historial de pases.
 *
 * Cubre: registro de pase, historial por radicado y autenticación.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-23
 */
class RadicaInternoPaseHistorialTest extends TestCase
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
            'Radicar -> Cores. Interna -> Editar',
            'Radicar -> Cores. Interna -> Mostrar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $role = Role::firstOrCreate(['name' => 'VentanillaInternoPase']);
        $role->givePermissionTo($permisos);

        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dirección General',
            'cod_organico' => 'DIRGEN',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista Internos',
            'cod_organico' => 'CINTE002',
            'parent' => $dependencia->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->user2 = User::factory()->create();
        $this->user2->assignRole($role);

        $trd = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-INT-P1',
            'nom' => 'Correspondencia Interna Pases',
            'user_register' => $this->user->id,
        ]);

        $this->userCargo = UserCargo::create([
            'user_id' => $this->user2->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        $this->radicado = VentanillaRadicaInterno::create([
            'num_radicado' => 'RAD-INP-'.uniqid(),
            'clasifica_documen_id' => $trd->id,
            'usuario_crea' => $this->user->id,
            'fec_docu' => now()->toDateString(),
            'fec_venci' => now()->addDays(10)->toDateString(),
            'asunto' => 'Radicado interno para pases',
        ]);
    }

    /** @test */
    public function puede_crear_pase()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/radica-interno/{$this->radicado->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo->id,
                'usuario_destino_id' => $this->user2->id,
                'tipo' => 'pase',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function puede_listar_historial_de_pases()
    {
        $this->actingAs($this->user)
            ->postJson("/api/ventanilla/radica-interno/{$this->radicado->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo->id,
                'usuario_destino_id' => $this->user2->id,
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/radica-interno/{$this->radicado->id}/pase-historial");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function falla_si_tipo_pase_invalido()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/radica-interno/{$this->radicado->id}/pase", [
                'users_cargos_destino_id' => $this->userCargo->id,
                'tipo' => 'tipo_inexistente',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $response = $this->getJson("/api/ventanilla/radica-interno/{$this->radicado->id}/pase-historial");
        $response->assertStatus(401);

        $response = $this->postJson("/api/ventanilla/radica-interno/{$this->radicado->id}/pase", []);
        $response->assertStatus(401);
    }
}


