<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-16: iniciarInstancia no miraba el estado del workflow; borradores y archivados se ejecutaban.
 * Además la instancia nacía apuntando al inicio ya completado y no podía avanzar.
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
        $inicio = $workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
        $paso = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $fin = $workflow->nodos()->create(['tipo' => 'fin', 'titulo' => 'Fin', 'posicion_x' => 0, 'posicion_y' => 0]);
        $workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $paso->id]);
        $workflow->conexiones()->create(['nodo_origen_id' => $paso->id, 'nodo_destino_id' => $fin->id]);

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

    public function test_la_instancia_arranca_en_el_nodo_siguiente_al_inicio_y_avanza(): void
    {
        $workflow = $this->workflowCon('activo');
        $paso = $workflow->nodos()->where('tipo', 'tarea')->first();

        $id = $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias")
            ->assertCreated()->json('data.id');

        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'nodo_actual_id' => $paso->id]);
        $this->assertDatabaseHas('workflow_nodos_instancia', ['instancia_id' => $id, 'nodo_id' => $paso->id, 'estado' => 'en_curso']);

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias/{$id}/ejecutar", ['nodo_id' => $paso->id])
            ->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada']);
    }
}
