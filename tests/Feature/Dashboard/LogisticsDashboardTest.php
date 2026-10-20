<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * /api/dashboard/logistics alimenta el dashboard Logistics con la correspondencia enviada
 * del alcance ABAC del usuario.
 */
class LogisticsDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_entrega_la_estructura_del_dashboard(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        $this->actingAs($user)->getJson('/api/dashboard/logistics')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'stats_horizontal' => [['title', 'stats', 'trendNumber', 'trendLabel', 'avatarIcon', 'color']],
                'vehicle_overview',
                'shipment_stats' => ['series', 'categories', 'total'],
                'delivery_performance' => [['title', 'value', 'change', 'icon', 'color']],
                'delivery_exceptions' => ['labels', 'series', 'total'],
                'orders_by_sedes' => ['pendientes', 'firmados', 'anulados'],
                'vehicles_table',
            ]])
            ->assertJsonCount(6, 'data.shipment_stats.categories');
    }

    public function test_los_envios_solo_incluyen_radicados_visibles_para_el_usuario(): void
    {
        $creador = User::factory()->create(['estado' => 1]);
        $ajeno = User::factory()->create(['estado' => 1]);
        $radicado = 'LOGI-'.uniqid();

        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_enviados')->insert([
            'num_radicado' => $radicado, 'clasifica_documen_id' => 1, 'tercero_id' => 1, 'medio_enviado_id' => 1,
            'tipo_respuesta_id' => 1, 'usuario_crea' => $creador->id, 'asunto' => 'Envío reservado',
            'estado_firma' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $radicados = fn (User $u) => collect($this->actingAs($u)->getJson('/api/dashboard/logistics')->assertOk()
            ->json('data.vehicles_table'))->pluck('location');

        $this->assertNotContains($radicado, $radicados($ajeno));
        $this->assertContains($radicado, $radicados($creador));
    }
}
