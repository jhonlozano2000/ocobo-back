<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * /api/dashboard/analytics alimenta el dashboard Analytics de la plantilla con datos reales:
 * conteos y vencimientos limitados al alcance ABAC del usuario.
 */
class AnalyticsDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private const CLAVES = [
        'slider', 'daily_chart', 'overview', 'earning_weekly', 'support_tracker',
        'dependencias', 'total_earning', 'canales', 'tipos_tramite', 'project_table',
    ];

    public function test_entrega_la_estructura_del_dashboard(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        $this->actingAs($user)->getJson('/api/dashboard/analytics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'slider' => [['title', 'img', 'details']],
                    'daily_chart' => ['series', 'categories', 'total_hoy'],
                    'overview' => ['titulo', 'total_documentos', 'trend', 'tendencia', 'tramitados', 'pendientes', 'porcentaje_tramitados', 'porcentaje_pendientes'],
                    'earning_weekly' => ['total', 'trend', 'tendencia', 'series', 'categories', 'cards'],
                    'support_tracker' => ['total_tickets', 'nuevas', 'tickets_abiertos', 'tiempo_respuesta', 'porcentaje_completado'],
                    'total_earning' => ['series', 'categories', 'total', 'trend', 'tendencia', 'total_recibidos', 'total_enviados'],
                ],
            ])
            ->assertJsonCount(7, 'data.daily_chart.categories')
            ->assertJsonCount(6, 'data.total_earning.categories');

        $this->assertEqualsCanonicalizing(self::CLAVES, array_keys($this->actingAs($user)->getJson('/api/dashboard/analytics')->json('data')));
    }

    public function test_un_radicado_ajeno_no_se_cuenta_ni_se_lista(): void
    {
        $creador = User::factory()->create(['estado' => 1]);
        $ajeno = User::factory()->create(['estado' => 1]);
        $radicado = 'ANL-'.uniqid();

        $datos = fn (User $u) => $this->actingAs($u)->getJson('/api/dashboard/analytics')->assertOk()->json('data');
        $recibidos = fn (array $d) => (int) $d['slider'][0]['details']['Recibidos'];

        $antesCreador = $recibidos($datos($creador));
        $antesAjeno = $recibidos($datos($ajeno));

        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_reci')->insert([
            'num_radicado' => $radicado, 'medio_recep_id' => 1, 'usuario_crea' => $creador->id,
            'asunto' => 'Asunto reservado', 'fec_venci' => now()->addDays(3)->toDateString(),
            'estado_trabajo' => 'RECIBIDO', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $delCreador = $datos($creador);
        $delAjeno = $datos($ajeno);

        $this->assertSame($antesCreador + 1, $recibidos($delCreador));
        $this->assertContains($radicado, array_column($delCreador['project_table'], 'title'));

        $this->assertSame($antesAjeno, $recibidos($delAjeno));
        $this->assertNotContains($radicado, array_column($delAjeno['project_table'], 'title'));
    }
}
