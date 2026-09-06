<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnulacionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $jefeArchivo;

    protected GestionTercero $tercero;

    protected ClasificacionDocumentalTRD $clasificacion;

    protected ConfigListaDetalle $medioRecepcion;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos necesarios
        Permission::firstOrCreate(['name' => 'Radicar -> Cores. Recibida -> Listar']);
        Permission::firstOrCreate(['name' => 'Radicar -> Cores. Enviada -> Listar']);
        Permission::firstOrCreate(['name' => 'Radicar -> Cores. Interna -> Listar']);
        Permission::firstOrCreate(['name' => 'Radicar -> PQRSF -> Anular']);
        Permission::firstOrCreate(['name' => 'Jefe de Archivo']);

        // Crear rol ventanilla
        $roleVentanilla = Role::firstOrCreate(['name' => 'Ventanilla Anulaciones']);
        $roleVentanilla->givePermissionTo([
            'Radicar -> Cores. Recibida -> Listar',
            'Radicar -> Cores. Enviada -> Listar',
            'Radicar -> Cores. Interna -> Listar',
            'Radicar -> PQRSF -> Anular',
        ]);

        // Crear rol Jefe de Archivo
        $roleJefe = Role::firstOrCreate(['name' => 'Jefe de Archivo Anulaciones']);
        $roleJefe->givePermissionTo(['Jefe de Archivo']);

        // Crear usuarios
        $this->user = User::factory()->create();
        $this->user->assignRole($roleVentanilla);

        $this->jefeArchivo = User::factory()->create();
        $this->jefeArchivo->assignRole($roleJefe);

        // Crear tercero de prueba
        $this->tercero = GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero Anulaciones',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        // Crear clasificación documental
        $this->clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-AN-01',
            'nom' => 'Serie Anulaciones Test',
            'dias_vencimiento' => 15,
            'user_register' => $this->user->id,
        ]);

        // Crear medio de recepción
        $listaMedios = ConfigLista::create([
            'nombre' => 'Medios Recepcion Anulaciones',
            'cod' => 'L-MED-AN',
        ]);
        $this->medioRecepcion = ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo electrónico',
            'codigo' => 'CORR-AN',
            'estado' => 1,
        ]);
    }

    // -------------------------------------------------------
    // RECADOS RECIBIDOS
    // -------------------------------------------------------

    /** @test */
    public function solicitar_anulacion_reci_requiere_auth(): void
    {
        $radicado = $this->crearRadicadoRecibido();

        $this->postJson("/api/ventanilla/radica-recibida/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Motivo de solicitud',
        ])->assertStatus(401);
    }

    /** @test */
    public function solicitar_anulacion_reci_crea_solicitud(): void
    {
        $radicado = $this->crearRadicadoRecibido();

        $response = $this->actingAs($this->user)->postJson("/api/ventanilla/radica-recibida/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Solicitud de prueba',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('ventanilla_radica_reci', [
            'id' => $radicado->id,
            'usua_soli_anula_id' => $this->user->id,
            'observa_soli_anula' => 'Solicitud de prueba',
        ]);
    }

    /** @test */
    public function solicitar_anulacion_reci_sin_permiso_falla(): void
    {
        $radicado = $this->crearRadicadoRecibido();
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->postJson("/api/ventanilla/radica-recibida/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Motivo',
        ])->assertStatus(403);
    }

    /** @test */
    public function procesar_aprobar_anula_radicado_reci(): void
    {
        $radicado = $this->crearRadicadoRecibidoConSolicitud();

        $response = $this->actingAs($this->jefeArchivo)->postJson("/api/ventanilla/radica-recibida/{$radicado->id}/procesar-anulacion", [
            'accion' => 'aprobar',
            'observa_aprue_anula' => 'Aprobado por jefe',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('ventanilla_radica_reci', [
            'id' => $radicado->id,
            'usua_aprue_anula_id' => $this->jefeArchivo->id,
            'estado_trabajo' => 'ANULADO',
        ]);
    }

    /** @test */
    public function procesar_rechazar_limpia_solicitud_reci(): void
    {
        $radicado = $this->crearRadicadoRecibidoConSolicitud();

        $response = $this->actingAs($this->jefeArchivo)->postJson("/api/ventanilla/radica-recibida/{$radicado->id}/procesar-anulacion", [
            'accion' => 'rechazar',
            'observa_aprue_anula' => 'Rechazado',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('ventanilla_radica_reci', [
            'id' => $radicado->id,
            'usua_soli_anula_id' => null,
            'observa_soli_anula' => null,
        ]);

        // No debe tener estado anulado
        $this->assertDatabaseMissing('ventanilla_radica_reci', [
            'id' => $radicado->id,
            'estado_trabajo' => 'ANULADO',
        ]);
    }

    /** @test */
    public function procesar_anulacion_reci_sin_permiso_falla(): void
    {
        $radicado = $this->crearRadicadoRecibidoConSolicitud();

        $this->actingAs($this->user)->postJson("/api/ventanilla/radica-recibida/{$radicado->id}/procesar-anulacion", [
            'accion' => 'aprobar',
            'observa_aprue_anula' => 'Sin permiso',
        ])->assertStatus(403);
    }

    /** @test */
    public function listar_pendientes_reci_retorna_datos(): void
    {
        $this->crearRadicadoRecibidoConSolicitud();

        $response = $this->actingAs($this->jefeArchivo)->getJson('/api/ventanilla/radica-recibida/pendientes-anulacion');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function listar_pendientes_reci_requiere_jefe_archivo(): void
    {
        $this->crearRadicadoRecibidoConSolicitud();

        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-recibida/pendientes-anulacion')
            ->assertStatus(403);
    }

    // -------------------------------------------------------
    // RADICADOS ENVIADOS
    // -------------------------------------------------------

    /** @test */
    public function solicitar_anulacion_envi_requiere_auth(): void
    {
        $radicado = $this->crearRadicadoEnviado();

        $this->postJson("/api/ventanilla/radica-enviada/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Motivo',
        ])->assertStatus(401);
    }

    /** @test */
    public function solicitar_anulacion_envi_crea_solicitud(): void
    {
        $radicado = $this->crearRadicadoEnviado();

        $this->actingAs($this->user)->postJson("/api/ventanilla/radica-enviada/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Solicitud enviados',
        ])->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_enviados', [
            'id' => $radicado->id,
            'usua_soli_anula_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function procesar_aprobar_anula_radicado_envi(): void
    {
        $radicado = $this->crearRadicadoEnviadoConSolicitud();

        $this->actingAs($this->jefeArchivo)->postJson("/api/ventanilla/radica-enviada/{$radicado->id}/procesar-anulacion", [
            'accion' => 'aprobar',
            'observa_aprue_anula' => 'Aprobado',
        ])->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_enviados', [
            'id' => $radicado->id,
            'usua_aprue_anula_id' => $this->jefeArchivo->id,
            'estado_trabajo' => 'ANULADO',
        ]);
    }

    /** @test */
    public function procesar_rechazar_limpia_solicitud_envi(): void
    {
        $radicado = $this->crearRadicadoEnviadoConSolicitud();

        $this->actingAs($this->jefeArchivo)->postJson("/api/ventanilla/radica-enviada/{$radicado->id}/procesar-anulacion", [
            'accion' => 'rechazar',
            'observa_aprue_anula' => 'Rechazado',
        ])->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_enviados', [
            'id' => $radicado->id,
            'usua_soli_anula_id' => null,
            'observa_soli_anula' => null,
        ]);

        $this->assertDatabaseMissing('ventanilla_radica_enviados', [
            'id' => $radicado->id,
            'estado_trabajo' => 'ANULADO',
        ]);
    }

    /** @test */
    public function listar_pendientes_envi_retorna_datos(): void
    {
        $this->crearRadicadoEnviadoConSolicitud();

        $this->actingAs($this->jefeArchivo)->getJson('/api/ventanilla/radica-enviada/pendientes-anulacion')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------
    // RADICADOS INTERNOS
    // -------------------------------------------------------

    /** @test */
    public function solicitar_anulacion_interno_requiere_auth(): void
    {
        $radicado = $this->crearRadicadoInterno();

        $this->postJson("/api/ventanilla/radica-interno/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Motivo',
        ])->assertStatus(401);
    }

    /** @test */
    public function solicitar_anulacion_interno_crea_solicitud(): void
    {
        $radicado = $this->crearRadicadoInterno();

        $this->actingAs($this->user)->postJson("/api/ventanilla/radica-interno/{$radicado->id}/solicitar-anulacion", [
            'observa_soli_anula' => 'Solicitud internos',
        ])->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_internos', [
            'id' => $radicado->id,
            'usua_soli_anula_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function procesar_aprobar_anula_radicado_interno(): void
    {
        $radicado = $this->crearRadicadoInternoConSolicitud();

        $this->actingAs($this->jefeArchivo)->postJson("/api/ventanilla/radica-interno/{$radicado->id}/procesar-anulacion", [
            'accion' => 'aprobar',
            'observa_aprue_anula' => 'Aprobado',
        ])->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_internos', [
            'id' => $radicado->id,
            'usua_aprue_anula_id' => $this->jefeArchivo->id,
            'estado_trabajo' => 'ANULADO',
        ]);
    }

    /** @test */
    public function procesar_rechazar_limpia_solicitud_interno(): void
    {
        $radicado = $this->crearRadicadoInternoConSolicitud();

        $this->actingAs($this->jefeArchivo)->postJson("/api/ventanilla/radica-interno/{$radicado->id}/procesar-anulacion", [
            'accion' => 'rechazar',
            'observa_aprue_anula' => 'Rechazado',
        ])->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_internos', [
            'id' => $radicado->id,
            'usua_soli_anula_id' => null,
            'observa_soli_anula' => null,
        ]);

        $this->assertDatabaseMissing('ventanilla_radica_internos', [
            'id' => $radicado->id,
            'estado_trabajo' => 'ANULADO',
        ]);
    }

    /** @test */
    public function listar_pendientes_interno_retorna_datos(): void
    {
        $this->crearRadicadoInternoConSolicitud();

        $this->actingAs($this->jefeArchivo)->getJson('/api/ventanilla/radica-interno/pendientes-anulacion')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------
    // PQRS
    // -------------------------------------------------------

    /** @test */
    public function solicitar_anulacion_pqrs_requiere_auth(): void
    {
        $pqrs = $this->crearPqrsVinculadaARadicado();

        $this->postJson("/api/ventanilla/pqrs/{$pqrs->ventanilla_radica_reci_id}/solicitar-anulacion", [
            'motivo' => 'Error de digitacion',
        ])->assertUnauthorized();
    }

    /** @test */
    public function solicitar_anulacion_pqrs_registra_solicitud_en_el_radicado(): void
    {
        $pqrs = $this->crearPqrsVinculadaARadicado();

        $response = $this->actingAs($this->user)->postJson(
            "/api/ventanilla/pqrs/{$pqrs->ventanilla_radica_reci_id}/solicitar-anulacion",
            ['motivo' => 'Error de digitacion']
        );

        $response->assertOk();
        $this->assertDatabaseHas('ventanilla_radica_reci', [
            'id' => $pqrs->ventanilla_radica_reci_id,
            'usua_soli_anula_id' => $this->user->id,
            'observa_soli_anula' => 'Error de digitacion',
        ]);
    }

    /** @test */
    public function listar_pendientes_anulacion_pqrs_exige_jefe_de_archivo(): void
    {
        $this->actingAs($this->user)->getJson('/api/ventanilla/pqrs/pendientes-anulacion')
            ->assertForbidden();
    }

    // -------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------

    private function crearRadicadoRecibido(): VentanillaRadicaReci
    {
        return VentanillaRadicaReci::create([
            'num_radicado' => 'REC-AN-'.random_int(100000, 999999),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medioRecepcion->id,
            'num_folios' => 3,
            'num_anexos' => 0,
            'asunto' => 'Radicado recibido para anulación',
            'usuario_crea' => $this->user->id,
            'estado_trabajo' => 'RECIBIDO',
        ]);
    }

    private function crearRadicadoRecibidoConSolicitud(): VentanillaRadicaReci
    {
        $radicado = $this->crearRadicadoRecibido();
        $radicado->update([
            'usua_soli_anula_id' => $this->user->id,
            'observa_soli_anula' => 'Solicitud de anulación',
        ]);

        return $radicado->fresh();
    }

    private function crearRadicadoEnviado(): VentanillaRadicaEnviados
    {
        // Crear tipo respuesta (FK NOT NULL)
        $listaTipos = ConfigLista::create(['nombre' => 'Tipos Respuesta Anulaciones', 'cod' => 'L-TRES-AN']);
        $tipoRespuesta = ConfigListaDetalle::create([
            'lista_id' => $listaTipos->id,
            'nombre' => 'Respuesta única',
            'codigo' => 'RES-AN',
            'estado' => 1,
        ]);

        return VentanillaRadicaEnviados::create([
            'num_radicado' => 'ENV-AN-'.random_int(100000, 999999),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_enviado_id' => $this->medioRecepcion->id,
            'tipo_respuesta_id' => $tipoRespuesta->id,
            'num_folios' => 2,
            'num_anexos' => 0,
            'asunto' => 'Radicado enviado para anulación',
            'usuario_crea' => $this->user->id,
            'estado_trabajo' => 'ENVIADO',
        ]);
    }

    private function crearRadicadoEnviadoConSolicitud(): VentanillaRadicaEnviados
    {
        $radicado = $this->crearRadicadoEnviado();
        $radicado->update([
            'usua_soli_anula_id' => $this->user->id,
            'observa_soli_anula' => 'Solicitud de anulación',
        ]);

        return $radicado->fresh();
    }

    private function crearRadicadoInterno(): VentanillaRadicaInterno
    {
        return VentanillaRadicaInterno::create([
            'num_radicado' => 'INT-AN-'.random_int(100000, 999999),
            'clasifica_documen_id' => $this->clasificacion->id,
            'asunto' => 'Radicado interno para anulación',
            'usuario_crea' => $this->user->id,
            'estado_trabajo' => 'ENVIADO',
        ]);
    }

    private function crearRadicadoInternoConSolicitud(): VentanillaRadicaInterno
    {
        $radicado = $this->crearRadicadoInterno();
        $radicado->update([
            'usua_soli_anula_id' => $this->user->id,
            'observa_soli_anula' => 'Solicitud de anulación',
        ]);

        return $radicado->fresh();
    }

    private function crearPqrsVinculadaARadicado(): VentanillaPqrs
    {
        $radicado = $this->crearRadicadoRecibido();

        $listaTipos = ConfigLista::create(['nombre' => 'Tipos PQRS Anulaciones', 'cod' => 'L-TPQ-AN']);
        $tipoPqrs = ConfigListaDetalle::create([
            'lista_id' => $listaTipos->id,
            'nombre' => 'Queja',
            'codigo' => 'PQ-AN',
            'estado' => 1,
        ]);

        return VentanillaPqrs::create([
            'ventanilla_radica_reci_id' => $radicado->id,
            'gestion_tercero_id' => $this->tercero->id,
            'clasificacion_documental_trd_id' => $this->clasificacion->id,
            'tipo_pqrs_id' => $tipoPqrs->id,
            'prioridad' => 'Normal',
            'estado_tramite' => 'Pendiente',
            'detalle_solicitud' => 'Solicitud PQRS para anulación',
            'fecha_vencimiento' => now()->addDays(15),
            'fecha_vencimiento_original' => now()->addDays(15),
            'fechor_tramite' => now(),
        ]);
    }
}
