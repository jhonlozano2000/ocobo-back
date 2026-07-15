<?php

namespace Tests\Feature\M14;

use App\Models\ReporteProgramado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Authentication ───────────────────────────────────────────

    public function test_unificado_requires_auth()
    {
        $response = $this->getJson('/api/reportes/unificado?modulo=recibidas');
        $response->assertUnauthorized();
    }

    public function test_export_requires_auth()
    {
        $response = $this->getJson('/api/reportes/export?modulo=recibidas&format=excel');
        $response->assertUnauthorized();
    }

    public function test_programados_list_requires_auth()
    {
        $response = $this->getJson('/api/reportes/programados');
        $response->assertUnauthorized();
    }

    // ─── Validación ────────────────────────────────────────────────

    public function test_unificado_requires_valid_modulo()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/reportes/unificado');
        $response->assertStatus(422);

        $response = $this->actingAs($user)->getJson('/api/reportes/unificado?modulo=invalid');
        $response->assertStatus(422);
    }

    public function test_export_requires_valid_format()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            '/api/reportes/export?modulo=recibidas&format=invalid'
        );
        $response->assertStatus(422);
    }

    public function test_export_accepts_valid_formats()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            '/api/reportes/export?modulo=recibidas&format=excel'
        );
        $response->assertStatus(200);
    }

    // ─── ReporteProgramado CRUD ────────────────────────────────────

    public function test_programados_list_empty()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/reportes/programados');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_create_programado()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reportes/programados', [
            'modulo' => 'recibidas',
            'filtros' => ['desde' => '2026-01-01'],
            'formato' => 'pdf',
            'periodicidad' => 'daily',
            'asunto' => 'Reporte diario de recibidas',
            'destinatarios' => ['user1@test.com'],
            'proxima_ejecucion' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.modulo', 'recibidas');
        $this->assertDatabaseHas('reportes_programados', ['asunto' => 'Reporte diario de recibidas']);
    }

    public function test_create_programado_requires_valid_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reportes/programados', []);
        $response->assertStatus(422);
    }

    public function test_update_programado()
    {
        $user = User::factory()->create();
        $item = ReporteProgramado::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/reportes/programados/{$item->id}", [
            'asunto' => 'Asunto actualizado',
            'formato' => 'excel',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.asunto', 'Asunto actualizado');
        $this->assertDatabaseHas('reportes_programados', [
            'id' => $item->id,
            'asunto' => 'Asunto actualizado',
            'formato' => 'excel',
        ]);
    }

    public function test_delete_programado()
    {
        $user = User::factory()->create();
        $item = ReporteProgramado::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/reportes/programados/{$item->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('reportes_programados', ['id' => $item->id]);
    }

    public function test_ejecutar_programado()
    {
        $user = User::factory()->create();
        $item = ReporteProgramado::factory()->create([
            'modulo' => 'recibidas',
            'formato' => 'pdf',
            'activo' => true,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/reportes/programados/{$item->id}/ejecutar"
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_ejecutar_invalid_programado_returns_404()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reportes/programados/99999/ejecutar');
        $response->assertNotFound();
    }

    // ─── Modelo ────────────────────────────────────────────────────

    public function test_calcular_proxima_ejecucion()
    {
        $item = new ReporteProgramado();
        $item->periodicidad = 'daily';
        $proxima = $item->calcularProximaEjecucion();
        $this->assertEquals(now()->addDay()->format('Y-m-d'), $proxima->format('Y-m-d'));

        $item->periodicidad = 'weekly';
        $proxima = $item->calcularProximaEjecucion();
        $this->assertEquals(now()->addDays(7)->format('Y-m-d'), $proxima->format('Y-m-d'));

        $item->periodicidad = 'monthly';
        $proxima = $item->calcularProximaEjecucion();
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), $proxima->format('Y-m-d'));

        $item->periodicidad = 'quarterly';
        $proxima = $item->calcularProximaEjecucion();
        $this->assertEquals(now()->addDays(90)->format('Y-m-d'), $proxima->format('Y-m-d'));
    }

    public function test_programado_cast_attributes()
    {
        $item = ReporteProgramado::factory()->create([
            'filtros' => ['desde' => '2026-01-01', 'estado' => 'Activo'],
            'destinatarios' => ['a@test.com', 'b@test.com'],
            'activo' => true,
        ]);

        $this->assertIsArray($item->filtros);
        $this->assertIsArray($item->destinatarios);
        $this->assertIsBool($item->activo);
        $this->assertEquals('2026-01-01', $item->filtros['desde']);
    }
}
