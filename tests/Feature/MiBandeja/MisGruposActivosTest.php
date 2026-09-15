<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use App\Models\MiBandeja\MiBandejaTempGrupoAprobador;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Mis Grupos Activos.
 *
 * Cubre:
 * - 19.24: Listar grupos activos donde el usuario es miembro
 * - IDOR: usuario ajeno no ve grupos de otros
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class MisGruposActivosTest extends TestCase
{
    protected User $user;

    protected User $userMiembro;

    protected User $userAjeno;

    protected VentanillaRadicaReci $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Mi Bandeja - Grupos Colaborativos -> Ver',
            'Mi Bandeja - Grupos Colaborativos -> Crear',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create(['email' => 'mga-user-' . uniqid() . '@test.com']);
        $this->user->givePermissionTo($permisos);

        $this->userMiembro = User::factory()->create(['email' => 'mga-miembro-' . uniqid() . '@test.com']);
        $this->userMiembro->givePermissionTo($permisos);

        $this->userAjeno = User::factory()->create(['email' => 'mga-ajeno-' . uniqid() . '@test.com']);
        $this->userAjeno->givePermissionTo($permisos);

        $listaMedios = ConfigLista::create([
            'nombre' => 'Tipos de Recepción',
            'cod' => 'L-MEDIO',
        ]);

        $detalleMedio = ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo',
            'codigo' => 'CORR',
            'estado' => 1,
        ]);

        $this->radicado = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-MGA-' . uniqid(),
            'usuario_crea' => $this->user->id,
            'medio_recep_id' => $detalleMedio->id,
        ]);
    }

    private function crearGrupoActivo(string $rol = 'revisor'): MiBandejaTemp
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/mi-bandeja/grupos-colaborativos', [
                'nombre' => 'Grupo Activo Test',
                'radicado_id' => $this->radicado->id,
                'radicado_tipo' => 'recibido',
            ]);

        $response->assertStatus(201);
        $grupoId = $response->json('data.id');

        // Marcar como activo (estado_grupo por defecto ya es activo en store)
        $grupo = MiBandejaTemp::findOrFail($grupoId);

        // Agregar miembro (creador siempre es miembro también)
        match ($rol) {
            'revisor' => MiBandejaTempGrupoRevisor::create([
                'grupo_id' => $grupoId,
                'user_id' => $this->userMiembro->id,
            ]),
            'firmante' => MiBandejaTempGrupoFirmante::create([
                'grupo_id' => $grupoId,
                'user_id' => $this->userMiembro->id,
                'orden_firma' => 1,
            ]),
            'proyector' => MiBandejaTempGrupoProyector::create([
                'grupo_id' => $grupoId,
                'user_id' => $this->userMiembro->id,
            ]),
            'aprobador' => MiBandejaTempGrupoAprobador::create([
                'grupo_id' => $grupoId,
                'user_id' => $this->userMiembro->id,
            ]),
        };

        // El creador también se agrega como revisor para que aparezca en mis-grupos-activos
        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $grupoId,
            'user_id' => $this->user->id,
        ]);

        return $grupo;
    }

    // ==================== AUTENTICACIÓN ====================

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos')
            ->assertStatus(401);
    }

    // ==================== INDEX ====================

    public function test_creador_ve_su_grupo_activo(): void
    {
        $grupo = $this->crearGrupoActivo();

        $response = $this->actingAs($this->user)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $grupo->id, $ids);
    }

    public function test_miembro_como_revisor_ve_el_grupo(): void
    {
        $grupo = $this->crearGrupoActivo('revisor');

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $grupo->id, $ids);
    }

    public function test_miembro_como_firmante_ve_el_grupo(): void
    {
        $grupo = $this->crearGrupoActivo('firmante');

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $grupo->id, $ids);
    }

    public function test_miembro_como_proyector_ve_el_grupo(): void
    {
        $grupo = $this->crearGrupoActivo('proyector');

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $grupo->id, $ids);
    }

    public function test_miembro_como_aprobador_ve_el_grupo(): void
    {
        $grupo = $this->crearGrupoActivo('aprobador');

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $grupo->id, $ids);
    }

    public function test_usuario_ajeno_no_ve_grupo(): void
    {
        $this->crearGrupoActivo();

        $response = $this->actingAs($this->userAjeno)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    public function test_grupo_inactivo_no_aparece(): void
    {
        $grupo = $this->crearGrupoActivo();

        // Marcar como inactivo
        $grupo->update(['estado_grupo' => 'inactivo']);

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains((int) $grupo->id, $ids);
    }

    public function test_respuesta_contiene_mi_rol(): void
    {
        $this->crearGrupoActivo('revisor');

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $primerGrupo = $response->json('data')[0];
        $this->assertEquals('revisor', $primerGrupo['mi_rol']);
    }

    public function test_respuesta_contiene_listas_de_miembros(): void
    {
        $this->crearGrupoActivo('revisor');

        $response = $this->actingAs($this->userMiembro)
            ->getJson('/api/mi-bandeja/grupos-colaborativos/mis-grupos-activos');

        $response->assertStatus(200);

        $primerGrupo = $response->json('data')[0];
        $this->assertArrayHasKey('revisores', $primerGrupo);
        $this->assertArrayHasKey('firmantes', $primerGrupo);
        $this->assertArrayHasKey('proyectores', $primerGrupo);
        $this->assertArrayHasKey('aprobadores', $primerGrupo);
    }
}
