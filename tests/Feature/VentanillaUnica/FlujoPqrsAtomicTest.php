<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\PqrsService;
use App\Services\VentanillaUnica\RadicadoConsecutivoService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlujoPqrsAtomicTest extends TestCase
{

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
        Permission::firstOrCreate(['name' => 'Radicar -> Cores. Recibida -> Ver Todos']);

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

        // Crear tercero de prueba (sin factory: modelo directo)
        $this->tercero = GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero de prueba',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        // Crear clasificación documental
        $this->clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-AT-01',
            'nom' => 'Serie Atomic Test',
            'dias_vencimiento' => 15,
            'user_register' => $this->user->id,
        ]);

        // Crear medio de recepción
        $listaMedios = ConfigLista::create([
            'nombre' => 'Tipos de Recepción Atomic',
            'cod' => 'L-MED-AT',
        ]);
        $this->medioRecepcion = ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo electrónico',
            'codigo' => 'CORR-AT',
            'estado' => 1,
        ]);

        // Crear tipo PQRS
        $listaTipos = ConfigLista::create([
            'nombre' => 'Tipos de PQRS Atomic',
            'cod' => 'L-TIPO-AT',
        ]);
        $this->tipoPqrs = ConfigListaDetalle::create([
            'lista_id' => $listaTipos->id,
            'nombre' => 'Peticion',
            'codigo' => 'PET-AT',
            'estado' => 1,
        ]);
    }

    /** @test */
    public function puede_crear_radicado_sin_pqrs()
    {
        $radicaCountBefore = DB::table('ventanilla_radica_reci')->count();
        $pqrsCountBefore = DB::table('ventanilla_pqrs')->count();

        $response = $this->actingAs($this->user)->postJson('/api/ventanilla/radica-recibida', [
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

        $this->assertSame($radicaCountBefore + 1, DB::table('ventanilla_radica_reci')->count());
        $this->assertSame($pqrsCountBefore, DB::table('ventanilla_pqrs')->count());
    }

    /** @test */
    public function el_consecutivo_reservado_se_revierte_si_la_transaccion_falla()
    {
        $service = app(RadicadoConsecutivoService::class);

        DB::beginTransaction();
        $reservado = $service->reservarRecibido();
        DB::rollBack();

        $confirmado = DB::transaction(fn () => $service->reservarRecibido());

        $this->assertSame($reservado, $confirmado);

        $consecutivo = DB::table('ventanilla_radicado_consecutivos')
            ->where('tipo', 'recibido')
            ->where('periodo', (int) now()->format('Y'))
            ->first();
        $this->assertNotNull($consecutivo);
        $this->assertGreaterThanOrEqual(1, $consecutivo->consecutivo);
    }

    /** @test */
    public function puede_crear_radicado_con_pqrs_exitosamente()
    {
        $radicaCountBefore = DB::table('ventanilla_radica_reci')->count();
        $pqrsCountBefore = DB::table('ventanilla_pqrs')->count();

        $response = $this->actingAs($this->user)->postJson('/api/ventanilla/radica-recibida', [
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

        $this->assertSame($radicaCountBefore + 1, DB::table('ventanilla_radica_reci')->count());
        $this->assertSame($pqrsCountBefore + 1, DB::table('ventanilla_pqrs')->count());

        // Verificar que la PQRS está vinculada al radicado
        $radicado = VentanillaRadicaReci::latest()->first();
        $this->assertTrue($radicado->pqrs()->exists());
    }

    /** @test */
    public function falla_creacion_pqrs_si_falta_tipo_pqrs()
    {
        $radicaCountBefore = DB::table('ventanilla_radica_reci')->count();
        $pqrsCountBefore = DB::table('ventanilla_pqrs')->count();

        $response = $this->actingAs($this->user)->postJson('/api/ventanilla/radica-recibida', [
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

        $this->assertSame($radicaCountBefore, DB::table('ventanilla_radica_reci')->count());
        $this->assertSame($pqrsCountBefore, DB::table('ventanilla_pqrs')->count());
    }

    /** @test */
    public function falla_creacion_pqrs_si_tipo_pqrs_no_existe()
    {
        $radicaCountBefore = DB::table('ventanilla_radica_reci')->count();
        $pqrsCountBefore = DB::table('ventanilla_pqrs')->count();

        $response = $this->actingAs($this->user)->postJson('/api/ventanilla/radica-recibida', [
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

        $this->assertSame($radicaCountBefore, DB::table('ventanilla_radica_reci')->count());
        $this->assertSame($pqrsCountBefore, DB::table('ventanilla_pqrs')->count());
    }

    /** @test */
    public function transaccion_es_atomica_si_pqrs_falla_radicado_tambien_se_revier()
    {
        // Simular un escenario donde PQRS falla después de crear el radicado
        // Esto se puede hacer mockeando el servicio PQRS para que lance una excepción

        $radicaCountBefore = DB::table('ventanilla_radica_reci')->count();
        $pqrsCountBefore = DB::table('ventanilla_pqrs')->count();

        $this->mock(PqrsService::class, function ($mock) {
            $mock->shouldReceive('crearDesdeRadicado')
                ->once()
                ->andThrow(new \Exception('Error simulado en PQRS'));
        });

        $response = $this->actingAs($this->user)->postJson('/api/ventanilla/radica-recibida', [
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Prueba de atomicidad',
            'crear_pqrs' => true,
            'tipo_pqrs_id' => $this->tipoPqrs->id,
            'prioridad' => 'Normal',
        ]);

        // La transacción debería hacer rollback completo.
        // El código exacto puede variar (500 por excepción del servicio o
        // 422 si una validación corta antes); lo crítico es que NO queden
        // filas parciales en ninguna de las dos tablas.
        $this->assertContains($response->status(), [500, 422]);

        $this->assertSame($radicaCountBefore, DB::table('ventanilla_radica_reci')->count());
        $this->assertSame($pqrsCountBefore, DB::table('ventanilla_pqrs')->count());
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
            'num_radicado' => 'ABAC-'.uniqid(),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Radicado propio',
        ]);

        $radicadoOtro = VentanillaRadicaReci::create([
            'num_radicado' => 'ABAC-'.uniqid(),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $otroUser->id,
            'num_folios' => 3,
            'num_anexos' => 1,
            'asunto' => 'Radicado de otro usuario',
        ]);

        // Verificar que el usuario solo ve su propio radicado
        $response = $this->actingAs($this->user)->getJson('/api/ventanilla/radica-recibida');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        // El usuario debería ver solo su propio radicado
        $responseData = $response->json('data');
        $asuntos = collect($responseData['data'])->pluck('asunto')->toArray();
        $this->assertContains('Radicado propio', $asuntos);
        $this->assertNotContains('Radicado de otro usuario', $asuntos);
    }

    /** @test */
    public function usuario_con_permiso_ver_todos_ve_todos_los_registros()
    {
        // Dar permiso de ver todos
        $this->user->givePermissionTo('Radicar -> Cores. Recibida -> Ver Todos');

        // Crear radicados de diferentes usuarios
        $otroUser = User::factory()->create();

        VentanillaRadicaReci::create([
            'num_radicado' => 'VERALL-'.uniqid(),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'num_folios' => 5,
            'num_anexos' => 2,
            'asunto' => 'Radicado propio',
        ]);

        VentanillaRadicaReci::create([
            'num_radicado' => 'VERALL-'.uniqid(),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $otroUser->id,
            'num_folios' => 3,
            'num_anexos' => 1,
            'asunto' => 'Radicado de otro usuario',
        ]);

        // Verificar que el usuario ve todos los radicados
        $response = $this->actingAs($this->user)->getJson('/api/ventanilla/radica-recibida');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $responseData = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $responseData['total']);
    }
}
