<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-45: StoreNodoRequest exigía nodos.*.id y conexiones.*.id, pero el builder envía client_id
 * (que además validated() descartaba) → guardar el canvas daba siempre 422 y ningún workflow tenía nodos.
 */
class GuardarCanvasTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo('Workflows -> Workflows -> Editar');
        $this->workflow = Workflow::create([
            'nombre' => 'Canvas M19-45', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id,
        ]);
    }

    /** Payload idéntico al de useWorkflowBuilder::toApiFormat. */
    private function payloadDelBuilder(): array
    {
        $nodo = fn (string $id, string $tipo, int $x) => [
            'client_id' => $id, 'tipo' => $tipo, 'titulo' => ucfirst($tipo), 'descripcion' => null,
            'posicion_x' => $x, 'posicion_y' => 100, 'configuracion_json' => [], 'orden_ejecucion' => 0,
        ];

        return [
            'nodos' => [$nodo('nodo_1', 'inicio', 0), $nodo('nodo_2', 'tarea', 200), $nodo('nodo_3', 'fin', 400)],
            'conexiones' => [
                ['nodo_origen_id' => 'nodo_1', 'nodo_destino_id' => 'nodo_2', 'etiqueta' => null, 'condicion_json' => null],
                ['nodo_origen_id' => 'nodo_2', 'nodo_destino_id' => 'nodo_3', 'etiqueta' => null, 'condicion_json' => null],
            ],
        ];
    }

    public function test_el_canvas_del_builder_se_guarda_con_nodos_y_conexiones(): void
    {
        $this->actingAs($this->creador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", $this->payloadDelBuilder())
            ->assertOk()
            ->assertJsonCount(3, 'data.nodos')
            ->assertJsonCount(2, 'data.conexiones');

        $inicio = $this->workflow->nodos()->where('tipo', 'inicio')->value('id');
        $tarea = $this->workflow->nodos()->where('tipo', 'tarea')->value('id');
        $this->assertDatabaseHas('workflow_conexiones', [
            'workflow_id' => $this->workflow->id, 'nodo_origen_id' => $inicio, 'nodo_destino_id' => $tarea,
        ]);
    }

    public function test_una_conexion_a_un_nodo_fuera_del_canvas_se_rechaza(): void
    {
        $payload = $this->payloadDelBuilder();
        $payload['conexiones'][0]['nodo_destino_id'] = '1';

        $this->actingAs($this->creador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('conexiones.0.nodo_destino_id');
    }

    public function test_un_canvas_vacio_se_puede_guardar(): void
    {
        $this->actingAs($this->creador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", ['nodos' => [], 'conexiones' => []])
            ->assertOk();
    }
}
