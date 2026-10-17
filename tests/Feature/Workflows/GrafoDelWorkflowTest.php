<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-18: el grafo se valida al guardar el canvas (completo si el workflow está activo) y al activarlo.
 */
class GrafoDelWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo('Workflows -> Workflows -> Editar');
    }

    private function workflow(string $estado): Workflow
    {
        return Workflow::create(['nombre' => "M19-18 {$estado}", 'estado' => $estado, 'creador_user_id' => $this->creador->id]);
    }

    private function canvas(array $tipos, array $conexiones): array
    {
        $nodos = [];
        foreach ($tipos as $clientId => $tipo) {
            $nodos[] = ['client_id' => $clientId, 'tipo' => $tipo, 'titulo' => $clientId, 'posicion_x' => 0, 'posicion_y' => 0];
        }

        return [
            'nodos' => $nodos,
            'conexiones' => array_map(fn ($c) => ['nodo_origen_id' => $c[0], 'nodo_destino_id' => $c[1]], $conexiones),
        ];
    }

    private function guardar(Workflow $workflow, array $canvas)
    {
        return $this->actingAs($this->creador)->postJson("/api/workflows/workflows/{$workflow->id}/canvas", $canvas);
    }

    public function test_un_borrador_se_guarda_a_medias_pero_no_inconsistente(): void
    {
        $workflow = $this->workflow('borrador');

        $this->guardar($workflow, $this->canvas(['n1' => 'inicio', 'n2' => 'tarea'], [['n1', 'n2']]))->assertOk();
        $this->guardar($workflow, $this->canvas(['n1' => 'inicio', 'n2' => 'inicio'], []))
            ->assertStatus(422)
            ->assertJsonValidationErrors('grafo');
    }

    public function test_un_workflow_activo_solo_guarda_un_grafo_completo(): void
    {
        $workflow = $this->workflow('activo');

        $this->guardar($workflow, $this->canvas(['n1' => 'inicio', 'n2' => 'tarea'], [['n1', 'n2']]))->assertStatus(422);
        $this->assertSame(0, $workflow->nodos()->count());

        $this->guardar($workflow, $this->canvas(['n1' => 'inicio', 'n2' => 'tarea', 'n3' => 'fin'], [['n1', 'n2'], ['n2', 'n3']]))
            ->assertOk();
    }

    public function test_solo_se_activa_un_grafo_completo(): void
    {
        $workflow = $this->workflow('borrador');
        $estado = fn () => $this->actingAs($this->creador)->patchJson("/api/workflows/workflows/{$workflow->id}/estado", ['estado' => 'activo']);

        $estado()->assertStatus(422)->assertJsonValidationErrors('grafo');
        $this->actingAs($this->creador)->putJson("/api/workflows/workflows/{$workflow->id}", ['estado' => 'activo'])->assertStatus(422)->assertJsonValidationErrors('grafo');
        $this->assertSame('borrador', $workflow->fresh()->estado);

        $this->guardar($workflow, $this->canvas(['n1' => 'inicio', 'n2' => 'fin'], [['n1', 'n2']]))->assertOk();
        $estado()->assertOk();
        $this->assertSame('activo', $workflow->fresh()->estado);
    }
}
