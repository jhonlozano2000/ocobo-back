<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-14: el CRUD de nodos sueltos alteraba el grafo bajo una instancia en curso
 * (el canvas ya lo impedía; las rutas de nodos no).
 */
class NodosConInstanciasEnCursoTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private Workflow $workflow;

    private WorkflowNodo $nodo;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo('Workflows -> Workflows -> Editar');

        $this->workflow = Workflow::create(['nombre' => 'M19-14', 'estado' => 'activo', 'creador_user_id' => $this->creador->id]);
        $this->nodo = $this->workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
    }

    private function base(): string
    {
        return "/api/workflows/workflows/{$this->workflow->id}/nodos";
    }

    private function instancia(string $estado): void
    {
        WorkflowInstancia::create([
            'workflow_id' => $this->workflow->id,
            'nodo_actual_id' => $this->nodo->id,
            'estado' => $estado,
            'usuario_ejecuta_id' => $this->creador->id,
            'fecha_inicio' => now(),
        ]);
    }

    public function test_con_una_instancia_en_curso_el_grafo_no_se_toca(): void
    {
        $this->instancia('en_curso');
        $this->actingAs($this->creador);

        $this->postJson($this->base(), ['tipo' => 'fin', 'titulo' => 'Fin', 'posicion_x' => 10, 'posicion_y' => 10])->assertStatus(422);
        $this->putJson("{$this->base()}/{$this->nodo->id}", ['titulo' => 'Cambiado'])->assertStatus(422);
        $this->deleteJson("{$this->base()}/{$this->nodo->id}")->assertStatus(422);

        $this->assertDatabaseHas('workflow_nodos', ['id' => $this->nodo->id, 'titulo' => 'Inicio']);
        $this->assertSame(1, $this->workflow->nodos()->count());
    }

    public function test_con_instancias_terminadas_se_sigue_editando(): void
    {
        $this->instancia('completada');

        $this->actingAs($this->creador)
            ->putJson("{$this->base()}/{$this->nodo->id}", ['titulo' => 'Inicio v2'])
            ->assertOk();
    }
}
