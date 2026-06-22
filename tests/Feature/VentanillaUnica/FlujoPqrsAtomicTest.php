<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\PqrsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlujoPqrsAtomicTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected GestionTercero $tercero;

    protected ClasificacionDocumentalTRD $clasificacion;

    protected ConfigListaDetalle $medioRecepcion;

    protected ConfigListaDetalle $tipoPqrs;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos necesarios
        Permission::firstOrCreate(['name' => 'Radicar -> Cores. Recibida -> Crear']);
        Permission::firstOrCreate(['name' => 'Radicar -> Cores. Recibida -> Listar']);
        Permission::firstOrCreate(['name' => 'Radicar -> PQRSF -> Listar']);
        Permission::firstOrCreate(['name' => 'Radicar -> PQRSF -> Crear']);
        Permission::firstOrCreate(['name' => 'Radicar -> Ver Todos']);

        // Crear rol con permisos
        $role = Role::firstOrCreate(['name' => 'Ventanilla']);
        $role->givePermissionTo([
            'Radicar -> Cores. Recibida -> Crear',
            'Radicar -> Cores. Recibida -> Listar',
            'Radicar -> PQRSF -> Listar',
            'Radicar -> PQRSF -> Crear',
        ]);

        // Crear usuario de prueba
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        // Crear tercero de prueba
        $this->tercero = GestionTercero::factory()->create();

        // Crear clasificación documental
        $this->clasificacion = ClasificacionDocumentalTRD::factory()->create([
            'dias_vencimiento' => 15,
        ]);

        // Crear medio de recepción
        $this->medioRecepcion = ConfigListaDetalle::factory()->create([
            'nombre' => 'Correo electrónico',
        ]);

        // Crear tipo PQRS
        $this->tipoPqrs = ConfigListaDetalle::factory()->create([
            'nombre' => 'Peticion',
        ]);
    }

    /** @test */
    public function puede_crear_radicado_sin_pqrs()
    {
        $response = $this->actingAs($this->user)->postJson('/api/radica-recibida', [
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'descrip_anexos' => 'Documentos de prueba',
            'asunto' => 'Prueba sin PQRS',
            'crear_pqrs' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.radicado.asunto', 'Prueba sin PQRS')
            ->assertJsonPath('data.pqrs', null);

        $this->assertDatabaseCount('ventanilla_radica_reci', 1);
        $this->assertDatabaseCount('ventanilla_pqrs', 0);
    }

    /** @test */
    public function puede_crear_radicado_con_pqrs_exitosamente()
    {
        $response = $this->actingAs($this->user)->postJson('/api/radica-recibida', [
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'descrip_anexos' => 'Documentos de prueba',
            'asunto' => 'Prueba con PQRS',
            'crear_pqrs' => true,
            'tipo_pqrs_id' => $this->tipoPqrs->id,
            'prioridad' => 'Normal',
            'fallo_judicial' => 'No',
            'observaciones_pqrs' => 'Observaciones de prueba',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.radicado.asunto', 'Prueba con PQRS')
            ->assertJsonPath('data.pqrs.estado_tramite', 'Pendiente');

        $this->assertDatabaseCount('ventanilla_radica_reci', 1);
        $this->assertDatabaseCount('ventanilla_pqrs', 1);

        // Verificar que la PQRS está vinculada al radicado
        $radicado = VentanillaRadicaReci::first();
        $this->assertTrue($radicado->pqrs()->exists());
    }

    /** @test */
    public function falla_creacion_pqrs_si_falta_tipo_pqrs()
    {
        $response = $this->actingAs($this->user)->postJson('/api/radica-recibida', [
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Prueba sin tipo PQRS',
            'crear_pqrs' => true,
            // Falta tipo_pqrs_id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_pqrs_id']);

        $this->assertDatabaseCount('ventanilla_radica_reci', 0);
        $this->assertDatabaseCount('ventanilla_pqrs', 0);
    }

    /** @test */
    public function falla_creacion_pqrs_si_tipo_pqrs_no_existe()
    {
        $response = $this->actingAs($this->user)->postJson('/api/radica-recibida', [
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Prueba con tipo PQRS inexistente',
            'crear_pqrs' => true,
            'tipo_pqrs_id' => 99999, // No existe
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_pqrs_id']);

        $this->assertDatabaseCount('ventanilla_radica_reci', 0);
        $this->assertDatabaseCount('ventanilla_pqrs', 0);
    }

    /** @test */
    public function transaccion_es_atomica_si_pqrs_falla_radicado_tambien_se_revier()
    {
        // Simular un escenario donde PQRS falla después de crear el radicado
        // Esto se puede hacer mockeando el servicio PQRS para que lance una excepción

        $this->mock(PqrsService::class, function ($mock) {
            $mock->shouldReceive('crearDesdeRadicado')
                ->once()
                ->andThrow(new \Exception('Error simulado en PQRS'));
        });

        $response = $this->actingAs($this->user)->postJson('/api/radica-recibida', [
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Prueba de atomicidad',
            'crear_pqrs' => true,
            'tipo_pqrs_id' => $this->tipoPqrs->id,
        ]);

        // La transacción debería hacer rollback completo
        $response->assertStatus(500);

        $this->assertDatabaseCount('ventanilla_radica_reci', 0);
        $this->assertDatabaseCount('ventanilla_pqrs', 0);
    }

    /** @test */
    public function abac_filtra_registros_por_dependencia()
    {
        // Crear estructura organizacional
        $dependenciaRaiz = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dirección General',
            'cod_organico' => 'DIR-001',
        ]);

        $dependenciaHija = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Oficina de Correspondencia',
            'cod_organico' => 'CORR-001',
            'parent' => $dependenciaRaiz->id,
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista',
            'cod_organico' => 'CARGO-001',
            'parent' => $dependenciaHija->id,
        ]);

        // Asignar cargo al usuario
        UserCargo::create([
            'user_id' => $this->user->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        // Crear otro usuario en diferente dependencia
        $otroUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Ventanilla']);
        $otroUser->assignRole($role);

        $otraDependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Otra Dependencia',
            'cod_organico' => 'OTRA-001',
        ]);

        $otroCargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Otro Cargo',
            'cod_organico' => 'CARGO-002',
            'parent' => $otraDependencia->id,
        ]);

        UserCargo::create([
            'user_id' => $otroUser->id,
            'cargo_id' => $otroCargo->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        // Crear radicados de ambos usuarios
        $radicadoPropio = VentanillaRadicaReci::create([
            'num_radicado' => '20260101-00001',
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Radicado propio',
        ]);

        $radicadoOtro = VentanillaRadicaReci::create([
            'num_radicado' => '20260101-00002',
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $otroUser->id,
            'num_folios' => 3,
            'num_anexos' => 1,
            'asunto' => 'Radicado de otro usuario',
        ]);

        // Verificar que el usuario solo ve su propio radicado
        $response = $this->actingAs($this->user)->getJson('/api/radica-recibida');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        // El usuario debería ver solo 1 radicado (el suyo)
        $responseData = $response->json('data');
        $this->assertEquals(1, $responseData['total']);
        $this->assertEquals('Radicado propio', $responseData['data'][0]['asunto']);
    }

    /** @test */
    public function usuario_con_permiso_ver_todos_ve_todos_los_registros()
    {
        // Dar permiso de ver todos
        $this->user->givePermissionTo('Radicar -> Ver Todos');

        // Crear radicados de diferentes usuarios
        $otroUser = User::factory()->create();

        VentanillaRadicaReci::create([
            'num_radicado' => '20260101-00001',
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Radicado propio',
        ]);

        VentanillaRadicaReci::create([
            'num_radicado' => '20260101-00002',
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $otroUser->id,
            'num_folios' => 3,
            'num_anexos' => 1,
            'asunto' => 'Radicado de otro usuario',
        ]);

        // Verificar que el usuario ve todos los radicados
        $response = $this->actingAs($this->user)->getJson('/api/radica-recibida');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $responseData = $response->json('data');
        $this->assertEquals(2, $responseData['total']);
    }
}
