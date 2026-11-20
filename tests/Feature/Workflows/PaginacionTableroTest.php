<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** La página 2 debe recuperar las tareas restantes incluso si todas comparten fecha. */
class PaginacionTableroTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recupera_mas_de_cien_tareas_sin_duplicados_y_con_orden_estable(): void
    {
        foreach (['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar']);
        $flow = Workflow::create(['nombre' => 'Tablero paginado', 'creador_user_id' => $user->id]);
        $date = now()->toDateTimeString();
        Tarea::insert(array_map(fn ($i) => ['workflow_id' => $flow->id, 'nombre' => 'Tarea '.$i, 'estado' => 'pendiente', 'created_at' => $date, 'updated_at' => $date], range(1, 125)));
        $expected = $flow->tareasModulo()->orderByDesc('id')->pluck('id')->all();
        $url = '/api/workflows/'.$flow->id.'/tareas?per_page=100';
        $first = $this->actingAs($user)->getJson($url.'&page=1')->assertOk()->assertJsonCount(100, 'data')
            ->assertJsonPath('pagination.total', 125)->assertJsonPath('pagination.last_page', 2)->json('data');
        $second = $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('pagination.current_page', 2)->json('data');
        $ids = array_column([...$first, ...$second], 'id');
        $this->assertSame($expected, $ids);
        $this->assertCount(125, array_unique($ids));
        $this->getJson($url.'&search=Tarea%20125')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('pagination.total', 1);
    }
}
