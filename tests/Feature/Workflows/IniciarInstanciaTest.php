<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-16: iniciarInstancia no miraba el estado del workflow; borradores y archivados se ejecutaban.
 */
class IniciarInstanciaTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    private function workflowCon(string $estado): Workflow
    {
        $workflow = Workflow::create(['nombre' => "M19-16 {$estado}", 'estado' => $estado, 'creador_user_id' => $this->usuario->id]);
        $workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);

        return $workflow;
    }

    public function test_no_se_inician_workflows_que_no_estan_activos(): void
    {
        foreach (['borrador', 'inactivo', 'archivado'] as $estado) {
            $workflow = $this->workflowCon($estado);

            $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias")
                ->assertStatus(422);
            $this->assertDatabaseMissing('workflow_instancias', ['workflow_id' => $workflow->id]);
        }
    }

    public function test_un_workflow_activo_se_inicia(): void
    {
        $workflow = $this->workflowCon('activo');

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias")
            ->assertCreated();
        $this->assertDatabaseHas('workflow_instancias', ['workflow_id' => $workflow->id, 'estado' => 'en_curso']);
    }
}
