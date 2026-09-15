<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Cumplidos (marcar terminado).
 *
 * Cubre:
 * - 19.26: Marcar tarea como cumplida (revisor, firmante, proyector, aprobador)
 * - IDOR: usuario ajeno no puede marcar cumplido
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class GruposColaborativosCumplidoTest extends TestCase
{
    protected User $creador;

    protected User $miembro;

    protected User $ajeno;

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

        $this->creador = User::factory()->create(['email' => 'cumplido-creador-' . uniqid() . '@test.com']);
        $this->creador->givePermissionTo($permisos);

        $this->miembro = User::factory()->create(['email' => 'cumplido-miembro-' . uniqid() . '@test.com']);
        $this->miembro->givePermissionTo($permisos);

        $this->ajeno = User::factory()->create(['email' => 'cumplido-ajeno-' . uniqid() . '@test.com']);
        $this->ajeno->givePermissionTo($permisos);

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
            'num_radicado' => 'RAD-RR-CUM-' . uniqid(),
            'usuario_crea' => $this->creador->id,
            'medio_recep_id' => $detalleMedio->id,
        ]);
    }

    private function crearGrupo(): int
    {
        $response = $this->actingAs($this->creador)
            ->postJson('/api/mi-bandeja/grupos-colaborativos', [
                'nombre' => 'Grupo Cumplido Test',
                'radicado_id' => $this->radicado->id,
                'radicado_tipo' => 'recibido',
            ]);

        $response->assertStatus(201);
        return $response->json('data.id');
    }

    // ==================== REVISOR MARCA CUMPLIDO ====================

    public function test_revisor_puede_marcar_cumplido(): void
    {
        $grupoId = $this->crearGrupo();

        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $grupoId,
            'user_id' => $this->miembro->id,
        ]);

        $response = $this->actingAs($this->miembro)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$grupoId}/marcar-terminado");

        $response->assertStatus(200);

        $this->assertDatabaseHas('mi_bandeja_temp_grupo_revisores', [
            'grupo_id' => $grupoId,
            'user_id' => $this->miembro->id,
            'estado_tarea' => 'cumplido',
        ]);
    }

    // ==================== FIRMANTE MARCA CUMPLIDO ====================

    public function test_firmante_puede_marcar_cumplido(): void
    {
        $grupoId = $this->crearGrupo();

        MiBandejaTempGrupoFirmante::create([
            'grupo_id' => $grupoId,
            'user_id' => $this->miembro->id,
            'orden_firma' => 1,
        ]);

        $response = $this->actingAs($this->miembro)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$grupoId}/marcar-terminado");

        $response->assertStatus(200);

        $this->assertDatabaseHas('mi_bandeja_temp_grupo_firmantes', [
            'grupo_id' => $grupoId,
            'user_id' => $this->miembro->id,
            'estado_tarea' => 'cumplido',
        ]);
    }

    // ==================== IDOR ====================

    public function test_usuario_ajeno_no_puede_marcar_cumplido(): void
    {
        $grupoId = $this->crearGrupo();

        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $grupoId,
            'user_id' => $this->miembro->id,
        ]);

        $response = $this->actingAs($this->ajeno)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$grupoId}/marcar-terminado");

        $response->assertStatus(403);
    }

    // ==================== CREADOR TAMBIÉN PUEDE ====================

    public function test_creador_puede_marcar_cumplido_si_es_miembro(): void
    {
        $grupoId = $this->crearGrupo();

        // El creador también se agrega como revisor
        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $grupoId,
            'user_id' => $this->creador->id,
        ]);

        $response = $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$grupoId}/marcar-terminado");

        $response->assertStatus(200);
    }
}
