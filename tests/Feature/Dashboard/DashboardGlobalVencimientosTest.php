<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RBAC-003: /api/dashboard/global mostraba a cualquier usuario autenticado el número y el asunto
 * de todos los radicados por vencer (y el detalle de las PQRS), en una caché común.
 * Ahora esa lista respeta el alcance ABAC de cada usuario.
 */
class DashboardGlobalVencimientosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_los_vencimientos_solo_incluyen_radicados_visibles_para_el_usuario(): void
    {
        Cache::flush();
        $creador = User::factory()->create(['estado' => 1]);
        $ajeno = User::factory()->create(['estado' => 1]);
        $asunto = 'Asunto reservado '.uniqid();

        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_reci')->insert([
            'num_radicado' => 'RBAC3D-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $creador->id,
            'asunto' => $asunto, 'fec_venci' => now()->addDays(3)->toDateString(), 'estado_trabajo' => 'PENDIENTE',
        ]);
        Schema::enableForeignKeyConstraints();

        $descripciones = fn (User $u) => collect($this->actingAs($u)->getJson('/api/dashboard/global')->assertOk()
            ->json('data.vencimientos_proximos'))->pluck('descripcion');

        // El ajeno consulta primero: si la lista se cachease en común, el creador no la vería
        $this->assertNotContains($asunto, $descripciones($ajeno));
        $this->assertContains($asunto, $descripciones($creador));
    }
}
