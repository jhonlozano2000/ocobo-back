<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\MiBandeja\MiBandejaTempNota;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Notas de Grupos Colaborativos.
 *
 * Cubre:
 * - 19.27: Listar notas, crear nota, IDOR
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class GruposColaborativosNotasTest extends TestCase
{
    protected User $creador;

    protected User $miembro;

    protected User $ajeno;

    protected VentanillaRadicaReci $radicado;

    protected int $grupoId;

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

        $this->creador = User::factory()->create(['email' => 'notas-creador-' . uniqid() . '@test.com']);
        $this->creador->givePermissionTo($permisos);

        $this->miembro = User::factory()->create(['email' => 'notas-miembro-' . uniqid() . '@test.com']);
        $this->miembro->givePermissionTo($permisos);

        $this->ajeno = User::factory()->create(['email' => 'notas-ajeno-' . uniqid() . '@test.com']);
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
            'num_radicado' => 'RAD-RR-NOT-' . uniqid(),
            'usuario_crea' => $this->creador->id,
            'medio_recep_id' => $detalleMedio->id,
        ]);

        // Crear grupo
        $response = $this->actingAs($this->creador)
            ->postJson('/api/mi-bandeja/grupos-colaborativos', [
                'nombre' => 'Grupo Notas Test',
                'radicado_id' => $this->radicado->id,
                'radicado_tipo' => 'recibido',
            ]);

        $response->assertStatus(201);
        $this->grupoId = $response->json('data.id');

        // Agregar miembro
        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $this->grupoId,
            'user_id' => $this->miembro->id,
        ]);
    }

    // ==================== LISTAR NOTAS ====================

    public function test_listar_notas_grupo_vacio(): void
    {
        $response = $this->actingAs($this->creador)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    public function test_listar_notas_grupo_con_notas(): void
    {
        MiBandejaTempNota::create([
            'grupo_id' => $this->grupoId,
            'user_id' => $this->creador->id,
            'contenido' => 'Primera nota',
        ]);

        MiBandejaTempNota::create([
            'grupo_id' => $this->grupoId,
            'user_id' => $this->miembro->id,
            'contenido' => 'Segunda nota',
        ]);

        $response = $this->actingAs($this->creador)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas");

        $response->assertStatus(200);

        $notas = $response->json('data');
        $this->assertCount(2, $notas);
        // Orden ascendente por created_at
        $this->assertEquals('Primera nota', $notas[0]['contenido']);
        $this->assertEquals('Segunda nota', $notas[1]['contenido']);
    }

    // ==================== CREAR NOTA ====================

    public function test_crear_nota(): void
    {
        $response = $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas", [
                'contenido' => 'Nota de prueba',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('mi_bandeja_temp_notas', [
            'grupo_id' => $this->grupoId,
            'user_id' => $this->creador->id,
            'contenido' => 'Nota de prueba',
        ]);
    }

    public function test_miembro_puede_crear_nota(): void
    {
        $response = $this->actingAs($this->miembro)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas", [
                'contenido' => 'Nota del miembro',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mi_bandeja_temp_notas', [
            'user_id' => $this->miembro->id,
            'contenido' => 'Nota del miembro',
        ]);
    }

    public function test_crear_nota_falla_sin_contenido(): void
    {
        $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas", [])
            ->assertStatus(422);
    }

    public function test_crear_nota_falla_con_contenido_largo(): void
    {
        $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas", [
                'contenido' => str_repeat('x', 5001),
            ])
            ->assertStatus(422);
    }

    // ==================== TYPING ====================

    public function test_typing_signal(): void
    {
        $response = $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas/typing", [
                'typing' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    // ==================== IDOR ====================

    public function test_usuario_ajeno_no_puede_listar_notas(): void
    {
        $response = $this->actingAs($this->ajeno)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas");

        $response->assertStatus(403);
    }

    public function test_usuario_ajeno_no_puede_crear_nota(): void
    {
        $response = $this->actingAs($this->ajeno)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/notas", [
                'contenido' => 'Nota intrusa',
            ]);

        $response->assertStatus(403);
    }
}
