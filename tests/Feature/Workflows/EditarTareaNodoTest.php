<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-04: UpdateWorkFlowTareaRequest aceptaba responsable_usuario_id, así que editar la tarea
 * la reasignaba sin el permiso Asignar que exige el endpoint /asignar.
 */
class EditarTareaNodoTest extends TestCase
{
    use DatabaseTransactions;

    private User $editor;

    private User $otro;

    private string $url;

    private int $tareaId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Editar', 'Asignar'] as $accion) {
            Permission::findOrCreate("Workflows -> Tareas -> {$accion}", 'web');
        }
        $this->editor = User::factory()->create(['estado' => 1]);
        $this->editor->givePermissionTo('Workflows -> Tareas -> Editar');
        $this->otro = User::factory()->create(['estado' => 1]);

        $workflow = Workflow::create([
            'nombre' => 'Tareas M19-04', 'estado' => 'borrador', 'creador_user_id' => $this->editor->id,
        ]);
        $nodo = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisión', 'posicion_x' => 0, 'posicion_y' => 0]);
        $this->tareaId = DB::table('work_flow_tareas')->insertGetId([
            'nodo_id' => $nodo->id, 'titulo' => 'Revisar', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->url = "/api/workflows/workflows/{$workflow->id}/nodos/{$nodo->id}/tareas/{$this->tareaId}";
    }

    public function test_editar_sin_permiso_asignar_no_cambia_el_responsable(): void
    {
        $this->actingAs($this->editor)
            ->putJson($this->url, ['titulo' => 'Revisar v2', 'responsable_usuario_id' => $this->otro->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('responsable_usuario_id');

        $this->assertDatabaseHas('work_flow_tareas', ['id' => $this->tareaId, 'responsable_usuario_id' => null]);
    }

    public function test_editar_sin_tocar_el_responsable_sigue_funcionando(): void
    {
        $this->actingAs($this->editor)->putJson($this->url, ['titulo' => 'Revisar v2'])->assertOk();

        $this->assertDatabaseHas('work_flow_tareas', ['id' => $this->tareaId, 'titulo' => 'Revisar v2']);
    }

    public function test_con_permiso_asignar_la_edicion_puede_reasignar(): void
    {
        $this->editor->givePermissionTo('Workflows -> Tareas -> Asignar');

        $this->actingAs($this->editor)
            ->putJson($this->url, ['responsable_usuario_id' => $this->otro->id])
            ->assertOk();

        $this->assertDatabaseHas('work_flow_tareas', ['id' => $this->tareaId, 'responsable_usuario_id' => $this->otro->id]);
    }
}
