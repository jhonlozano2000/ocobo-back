<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * /api/dashboard/crm alimenta el dashboard CRM de la plantilla con datos reales: las listas con
 * radicados respetan el alcance ABAC y los conteos salen de la base, no de valores de ejemplo.
 */
class CrmDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_entrega_la_estructura_que_consume_el_dashboard(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        $this->actingAs($user)->getJson('/api/dashboard/crm')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'distributed_orders' => ['series', 'total', 'trend', 'trend_status'],
                'yearly_sales' => ['series', 'categories', 'total', 'trend', 'trend_status'],
                'card_stats' => [
                    'pqrs_activas' => ['title', 'subtitle', 'stats', 'chipText', 'chipColor'],
                    'firmas' => ['title', 'subtitle', 'stats', 'chipText', 'chipColor'],
                ],
                'revenue_growth' => ['series', 'categories', 'total', 'trend', 'trend_status'],
                'tab_data' => [['type', 'label', 'avatarIcon', 'categories', 'series']],
                'radar_data' => ['categories', 'series'],
                'dependencias',
                'project_status' => ['total_docs', 'trend', 'trend_status', 'categories', 'series', 'items'],
                'active_projects',
                'last_transactions',
                'activity_timeline',
            ]]);
    }

    public function test_los_ultimos_radicados_solo_incluyen_los_visibles_para_el_usuario(): void
    {
        $creador = User::factory()->create(['estado' => 1]);
        $ajeno = User::factory()->create(['estado' => 1]);
        $numero = 'CRM-'.uniqid();

        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_reci')->insert([
            'num_radicado' => $numero, 'medio_recep_id' => 1, 'usuario_crea' => $creador->id,
            'asunto' => 'Asunto reservado', 'estado_trabajo' => 'RECIBIDO', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $radicados = fn (User $u) => collect($this->actingAs($u)->getJson('/api/dashboard/crm')->assertOk()
            ->json('data.last_transactions'))->pluck('radicado');

        $this->assertNotContains($numero, $radicados($ajeno));
        $this->assertContains($numero, $radicados($creador));

        // El recibido de hoy cuenta en la radicación diaria del creador y no en la del ajeno
        $hoy = fn (User $u) => collect($this->actingAs($u)->getJson('/api/dashboard/crm')
            ->json('data.distributed_orders.series.0.data'))->last();
        $this->assertSame(1, $hoy($creador));
        $this->assertSame(0, $hoy($ajeno));
    }
}
