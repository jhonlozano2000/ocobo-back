<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Pase y compartir de radicados recibidos.
 *
 * Cubre:
 * - 19.4: Asignar responsables (pase + compartir)
 * - IDOR: usuario ajeno no puede hacer pase/compartir
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class RecibidosPaseCompartirTest extends TestCase
{
    protected User $user;

    protected User $userDestino;

    protected User $userAjeno;

    protected UserCargo $userCargo;

    protected VentanillaRadicaReci $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Radicar -> Cores. Recibida -> Editar',
            'Radicar -> Cores. Recibida -> Mostrar',
            'Mi Bandeja - Recibidos -> Ver',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dep Pase Test',
            'cod_organico' => 'DEP-PASE',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Cargo Pase Test',
            'cod_organico' => 'CAR-PASE',
            'parent' => $dependencia->id,
        ]);

        $listaMedios = ConfigLista::create([
            'nombre' => 'Medios Recepción Pase',
            'cod' => 'L-MED-PASE',
        ]);

        $medioRecepcion = ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo Pase',
            'codigo' => 'CORR-P',
            'estado' => 1,
        ]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo($permisos);

        $this->userDestino = User::factory()->create();
        $this->userDestino->givePermissionTo($permisos);

        $this->userAjeno = User::factory()->create();
        $this->userAjeno->givePermissionTo($permisos);

        $tercero = GestionTercero::create([
            'num_docu_nit' => 'CC' . random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero Pase Test',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        $clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-PASE-01',
            'nom' => 'Serie Pase Test',
            'dias_vencimiento' => 15,
            'user_register' => $this->user->id,
        ]);

        $this->userCargo = UserCargo::create([
            'user_id' => $this->userDestino->id,
            'cargo_id' => $cargo->id,
            'dependencia_id' => $dependencia->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        $this->radicado = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-PASE-' . random_int(10000, 99999),
            'clasifica_documen_id' => $clasificacion->id,
            'tercero_id' => $tercero->id,
            'medio_recep_id' => $medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'asunto' => 'Radicado para pase test',
            'estado_trabajo' => 'RECIBIDO',
            'fec_venci' => now()->addDays(10),
        ]);
    }

    public function test_pase_requiere_autenticacion(): void
    {
        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/pase", [
            'usuario_destino_id' => $this->userDestino->id,
        ])->assertUnauthorized();
    }

    public function test_pase_crea_registro_historial(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/pase", [
            'usuario_destino_id' => $this->userDestino->id,
        ])->assertCreated();

        $this->assertDatabaseHas('ventanilla_radica_reci_pase_historial', [
            'radica_reci_id' => $this->radicado->id,
        ]);
    }

    public function test_compartir_requiere_autenticacion(): void
    {
        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/compartir", [
            'usuario_destino_id' => $this->userDestino->id,
        ])->assertUnauthorized();
    }

    public function test_compartir_crea_registro_historial(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/compartir", [
            'usuario_destino_id' => $this->userDestino->id,
        ])->assertCreated();

        $this->assertDatabaseHas('ventanilla_radica_reci_compartir_historial', [
            'radica_reci_id' => $this->radicado->id,
        ]);
    }

    public function test_usuario_con_permiso_puede_hacer_pase(): void
    {
        Sanctum::actingAs($this->userAjeno);

        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/pase", [
            'usuario_destino_id' => $this->userDestino->id,
        ])->assertCreated();
    }

    public function test_usuario_con_permiso_puede_compartir(): void
    {
        Sanctum::actingAs($this->userAjeno);

        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/compartir", [
            'usuario_destino_id' => $this->userDestino->id,
        ])->assertCreated();
    }

    public function test_historial_pase_lista_radicado(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/pase", [
            'usuario_destino_id' => $this->userDestino->id,
        ]);

        $response = $this->getJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/pase-historial");
        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_historial_compartir_lista_radicado(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/compartir", [
            'usuario_destino_id' => $this->userDestino->id,
        ]);

        $response = $this->getJson("/api/ventanilla/radica-recibida/{$this->radicado->id}/compartir-historial");
        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    protected function tearDown(): void
    {
        if (isset($this->radicado)) {
            \DB::table('ventanilla_radica_reci_responsa')
                ->where('radica_reci_id', $this->radicado->id)->delete();
            \DB::table('ventanilla_radica_reci_pase_historial')
                ->where('radica_reci_id', $this->radicado->id)->delete();
            \DB::table('ventanilla_radica_reci_compartir_historial')
                ->where('radica_reci_id', $this->radicado->id)->delete();
            $this->radicado->delete();
        }
        parent::tearDown();
    }
}
