<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados as VentanillaRadicaEnviado;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsable;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Estadísticas y listados paginados.
 *
 * Cubre:
 * - 19.1/19.9/19.14: Listar radicados con paginación real
 * - 19.8/19.13/19.21: Estadísticas con contadores reales
 * - Filtro por nivel (mis_radicados / ver_todo)
 * - Autenticación y permisos por sección
 *
 * Notas de diseño:
 * - Recibidos usa `usuariosResponsables` (belongsToMany a UserCargo) y su boot
 *   method sobreescribe `estado_trabajo` al crear → todos quedan en RECIBIDO.
 * - Enviados usa `usuariosResponsables` (belongsToMany a UserCargo).
 * - Internos usa `responsables` (hasMany a VentanillaRadicaInternoResponsable).
 *
 * @author  Jhon Javer Lozano Arce
 *
 * @date    2026-09-14
 */
class MiBandejaEstadisticasTest extends TestCase
{

    protected User $user;

    protected User $userSubordinado;

    protected UserCargo $userCargo;

    protected UserCargo $userCargoSubordinado;

    protected VentanillaRadicaReci $radicadoRecibido;

    protected VentanillaRadicaEnviado $radicadoEnviado;

    protected VentanillaRadicaInterno $radicadoInterno;

    /**
     * Configuración base: crea permisos, estructura organizacional,
     * catálogos FK (clasificación documental, tercero, medios),
     * usuarios con permisos y radicados de prueba.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar caché de permisos de Spatie entre tests
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // --- Permisos necesarios (deben coincidir con los middleware de rutas) ---
        $permisos = [
            'Mi Bandeja - Recibidos -> Ver',
            'Mi Bandeja - Enviados -> Ver',
            'Mi Bandeja - Internos -> Ver',
        ];

        // Permisos usados por MiBandejaFiltroService en ver_todo
        Permission::firstOrCreate(['name' => 'Mi Bandeja -> Jefe de dependencia']);
        Permission::firstOrCreate(['name' => 'Mi Bandeja -> Jefe de oficina']);

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // --- Estructura organizacional (CalidadOrganigrama) ---
        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dep Test',
            'cod_organico' => 'DEP001',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Cargo Test',
            'cod_organico' => 'CAR001',
            'parent' => $dependencia->id,
        ]);

        // --- Catálogo de medios de recepción ---
        $listaMedios = ConfigLista::create([
            'nombre' => 'Tipos de Recepción',
            'cod' => 'L-MEDIO',
        ]);

        $medioRecepcion = ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo',
            'codigo' => 'CORR',
            'estado' => 1,
        ]);

        // --- Catálogo de tipo de respuesta (requerido por VentanillaRadicaEnviados) ---
        $listaTipoRespuesta = ConfigLista::create([
            'nombre' => 'Tipo Respuesta',
            'cod' => 'L-TIP-RESP',
        ]);

        $tipoRespuesta = ConfigListaDetalle::create([
            'lista_id' => $listaTipoRespuesta->id,
            'nombre' => 'Respuesta General',
            'codigo' => 'RESP-G',
            'estado' => 1,
        ]);

        // --- Usuarios principales ---
        $this->user = User::factory()->create();
        $this->user->givePermissionTo($permisos);

        // Permiso de jefe para poder usar ver_todo en tests
        $this->user->givePermissionTo('Mi Bandeja -> Jefe de dependencia');

        // --- Datos FK requeridos por VentanillaRadicaEnviados ---
        $tercero = GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero Test M19',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        $clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-M19-01',
            'nom' => 'Serie Mi Bandeja Test',
            'dias_vencimiento' => 15,
            'user_register' => $this->user->id,
        ]);

        // --- Asignación de cargo al usuario principal ---
        $this->userCargo = UserCargo::create([
            'user_id' => $this->user->id,
            'cargo_id' => $cargo->id,
            'dependencia_id' => $dependencia->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        // --- Usuario subordinado (para tests de ver_todo) ---
        $this->userSubordinado = User::factory()->create();
        $this->userSubordinado->givePermissionTo($permisos);

        $this->userCargoSubordinado = UserCargo::create([
            'user_id' => $this->userSubordinado->id,
            'cargo_id' => $cargo->id,
            'dependencia_id' => $dependencia->id,
            'supervisor_id' => $this->user->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        // ==================== RECIBIDOS ====================
        // El boot method de VentanillaRadicaReci sobreescribe estado_trabajo
        // al crear. Con fec_venci y sin MiBandejaTemp → queda RECIBIDO.
        $this->radicadoRecibido = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'medio_recep_id' => $medioRecepcion->id,
            'fec_venci' => now()->addDays(15),
        ]);
        $this->radicadoRecibido->usuariosResponsables()->attach($this->userCargo->id);

        $r2 = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'medio_recep_id' => $medioRecepcion->id,
            'fec_venci' => now()->addDays(15),
        ]);
        $r2->usuariosResponsables()->attach($this->userCargo->id);

        $r3 = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'medio_recep_id' => $medioRecepcion->id,
            'fec_venci' => now()->addDays(15),
        ]);
        $r3->usuariosResponsables()->attach($this->userCargo->id);

        // ==================== ENVIADOS (1 PENDIENTE, 1 EN_PROCESO) ====================
        // VentanillaRadicaEnviados NO tiene boot override de estado_trabajo.
        $this->radicadoEnviado = VentanillaRadicaEnviado::create([
            'num_radicado' => 'RAD-RE-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'clasifica_documen_id' => $clasificacion->id,
            'tercero_id' => $tercero->id,
            'medio_enviado_id' => $medioRecepcion->id,
            'tipo_respuesta_id' => $tipoRespuesta->id,
            'estado_trabajo' => 'PENDIENTE',
        ]);
        $this->radicadoEnviado->usuariosResponsables()->attach($this->userCargo->id);

        $e2 = VentanillaRadicaEnviado::create([
            'num_radicado' => 'RAD-RE-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'clasifica_documen_id' => $clasificacion->id,
            'tercero_id' => $tercero->id,
            'medio_enviado_id' => $medioRecepcion->id,
            'tipo_respuesta_id' => $tipoRespuesta->id,
            'estado_trabajo' => 'EN_PROCESO',
        ]);
        $e2->usuariosResponsables()->attach($this->userCargo->id);

        // ==================== INTERNOS (1 BORRADOR, 1 EN_PROCESO, 1 FINALIZADO) ====================
        // VentanillaRadicaInterno NO tiene boot override de estado_trabajo.
        $this->radicadoInterno = VentanillaRadicaInterno::create([
            'num_radicado' => 'RAD-RI-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'clasifica_documen_id' => $clasificacion->id,
            'estado_trabajo' => 'BORRADOR',
        ]);

        $i2 = VentanillaRadicaInterno::create([
            'num_radicado' => 'RAD-RI-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'clasifica_documen_id' => $clasificacion->id,
            'estado_trabajo' => 'EN_PROCESO',
        ]);

        $i3 = VentanillaRadicaInterno::create([
            'num_radicado' => 'RAD-RI-'.uniqid(),
            'usuario_crea' => $this->user->id,
            'clasifica_documen_id' => $clasificacion->id,
            'estado_trabajo' => 'FINALIZADO',
        ]);

        // Internos usa responsables (hasMany a VentanillaRadicaInternoResponsable)
        VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $this->radicadoInterno->id,
            'users_cargos_id' => $this->userCargo->id,
        ]);
        VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $i2->id,
            'users_cargos_id' => $this->userCargo->id,
        ]);
        VentanillaRadicaInternoResponsable::create([
            'radica_interno_id' => $i3->id,
            'users_cargos_id' => $this->userCargo->id,
        ]);
    }

    // ==================== RECIBIDOS ====================

    public function test_recibidos_requiere_autenticacion(): void
    {
        $this->getJson('/api/mi-bandeja/recibidos/mis-radicados')
            ->assertUnauthorized();
    }

    public function test_recibidos_lista_con_paginacion(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/recibidos/mis-radicados?per_page=2');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);

        $this->assertEquals(2, $response->json('pagination.per_page'));
        $this->assertGreaterThanOrEqual(3, $response->json('pagination.total'));
    }

    public function test_recibidos_estadisticas_contadores_reales(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/recibidos/mis-radicados/estadisticas');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => ['total', 'recibido', 'enProceso', 'finalizado'],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, $data['total']);
        // Los 3 quedan RECIBIDO porque el boot method los sobreescribe
        // (sin fec_venci y responsables solo se vinculan después del create)
        $this->assertGreaterThanOrEqual(3, $data['recibido']);
    }

    public function test_recibidos_filtro_por_estado(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/recibidos/mis-radicados?estado=RECIBIDO');

        $response->assertOk()->assertJsonPath('status', true);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_recibidos_filtro_por_search(): void
    {
        Sanctum::actingAs($this->user);

        $numRadicado = $this->radicadoRecibido->num_radicado;
        $response = $this->getJson("/api/mi-bandeja/recibidos/mis-radicados?search={$numRadicado}");

        $response->assertOk()->assertJsonPath('status', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ==================== ENVIADOS ====================

    public function test_enviados_requiere_autenticacion(): void
    {
        $this->getJson('/api/mi-bandeja/enviados/mis-radicados')
            ->assertUnauthorized();
    }

    public function test_enviados_lista_con_paginacion(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/enviados/mis-radicados?per_page=10');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('pagination.total'));
    }

    public function test_enviados_estadisticas_contadores_reales(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/enviados/mis-radicados/estadisticas');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => ['total', 'pendiente', 'enProceso', 'finalizado'],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $data['total']);
        $this->assertGreaterThanOrEqual(1, $data['pendiente']);
        $this->assertGreaterThanOrEqual(1, $data['enProceso']);
    }

    // ==================== INTERNOS ====================

    public function test_internos_requiere_autenticacion(): void
    {
        $this->getJson('/api/mi-bandeja/internos/mis-radicados')
            ->assertUnauthorized();
    }

    public function test_internos_lista_con_paginacion(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/internos/mis-radicados?per_page=2');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);

        $this->assertGreaterThanOrEqual(3, $response->json('pagination.total'));
    }

    public function test_internos_estadisticas_contadores_reales(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/internos/mis-radicados/estadisticas');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => ['total', 'borrador', 'enProceso', 'finalizado'],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, $data['total']);
        $this->assertGreaterThanOrEqual(1, $data['borrador']);
        $this->assertGreaterThanOrEqual(1, $data['enProceso']);
        $this->assertGreaterThanOrEqual(1, $data['finalizado']);
    }

    // ==================== VER_TODO ====================

    public function test_recibidos_ver_todo_ve_radicados_subordinados(): void
    {
        Sanctum::actingAs($this->user);

        VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-'.uniqid(),
            'usuario_crea' => $this->userSubordinado->id,
            'medio_recep_id' => $this->radicadoRecibido->medio_recep_id,
            'fec_venci' => now()->addDays(15),
        ])->usuariosResponsables()->attach($this->userCargoSubordinado->id);

        $response = $this->getJson('/api/mi-bandeja/recibidos/mis-radicados?nivel=ver_todo');

        $response->assertOk()->assertJsonPath('status', true);
        $this->assertGreaterThanOrEqual(4, $response->json('pagination.total'));
    }

    public function test_estadisticas_ver_todo_incluye_subordinados(): void
    {
        Sanctum::actingAs($this->user);

        VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-'.uniqid(),
            'usuario_crea' => $this->userSubordinado->id,
            'medio_recep_id' => $this->radicadoRecibido->medio_recep_id,
            'fec_venci' => now()->addDays(15),
        ])->usuariosResponsables()->attach($this->userCargoSubordinado->id);

        $response = $this->getJson('/api/mi-bandeja/recibidos/mis-radicados/estadisticas?nivel=ver_todo');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(4, $data['total']);
    }

    // ==================== PERMISOS ====================

    public function test_usuario_sin_permiso_recibidos_no_puede_acceder(): void
    {
        $userSinPermiso = User::factory()->create();
        Sanctum::actingAs($userSinPermiso);

        $this->getJson('/api/mi-bandeja/recibidos/mis-radicados')
            ->assertForbidden();
    }

    public function test_usuario_sin_permiso_enviados_no_puede_acceder(): void
    {
        $userSinPermiso = User::factory()->create();
        Sanctum::actingAs($userSinPermiso);

        $this->getJson('/api/mi-bandeja/enviados/mis-radicados')
            ->assertForbidden();
    }

    public function test_usuario_sin_permiso_internos_no_puede_acceder(): void
    {
        $userSinPermiso = User::factory()->create();
        Sanctum::actingAs($userSinPermiso);

        $this->getJson('/api/mi-bandeja/internos/mis-radicados')
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        if (isset($this->radicadoRecibido)) {
            \DB::table('ventanilla_radica_reci_responsa')
                ->where('radica_reci_id', $this->radicadoRecibido->id)->delete();
            $this->radicadoRecibido->delete();
        }
        if (isset($this->radicadoEnviado)) {
            $this->radicadoEnviado->delete();
        }
        if (isset($this->radicadoInterno)) {
            \DB::table('ventanilla_radica_internos_responsa')
                ->where('radica_interno_id', $this->radicadoInterno->id)->delete();
            $this->radicadoInterno->delete();
        }
        parent::tearDown();
    }
}
