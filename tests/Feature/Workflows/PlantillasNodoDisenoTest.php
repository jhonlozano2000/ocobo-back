<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkFlowTarea;
use App\Models\Workflows\WorkFlowTareaChecklist;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-53: cambiar el estado de una tarea de nodo y editar un ítem de su checklist solo exigían el
 * permiso plano; cualquier editor modificaba las plantillas de un workflow ajeno.
 */
class PlantillasNodoDisenoTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private User $editor;

    private Workflow $workflow;

    private WorkFlowTarea $plantilla;

    private WorkFlowTareaChecklist $item;

    protected function setUp(): void
    {
        parent::setUp();

        $permisos = ['Workflows -> Tareas -> Editar', 'Workflows -> Workflows -> Editar'];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        [$this->creador, $this->editor] = User::factory()->count(2)->create(['estado' => 1])->all();
        $this->creador->givePermissionTo($permisos);
        $this->editor->givePermissionTo($permisos);

        $this->workflow = Workflow::create(['nombre' => 'M19-53', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id]);
        $nodo = $this->workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $this->plantilla = WorkFlowTarea::create(['nodo_id' => $nodo->id, 'titulo' => 'Revisar anexo', 'estado' => 'pendiente', 'orden' => 0]);
        $this->item = WorkFlowTareaChecklist::create(['work_flow_tarea_id' => $this->plantilla->id, 'item_descripcion' => 'Leer', 'esta_completado' => false, 'orden' => 0]);
    }

    private function url(): string
    {
        return "/api/workflows/workflows/{$this->workflow->id}/nodos/{$this->plantilla->nodo_id}/tareas/{$this->plantilla->id}";
    }

    public function test_otro_editor_no_modifica_las_plantillas(): void
    {
        $this->actingAs($this->editor)->patchJson("{$this->url()}/estado", ['estado' => 'en_curso'])->assertForbidden();
        $this->actingAs($this->editor)->putJson("{$this->url()}/checklists/{$this->item->id}", ['item_descripcion' => 'Otra'])->assertForbidden();

        $this->assertDatabaseHas('work_flow_tareas', ['id' => $this->plantilla->id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('work_flow_tarea_checklists', ['id' => $this->item->id, 'item_descripcion' => 'Leer']);
    }

    public function test_el_creador_si(): void
    {
        $this->actingAs($this->creador)->patchJson("{$this->url()}/estado", ['estado' => 'en_curso'])->assertOk();
        $this->actingAs($this->creador)->putJson("{$this->url()}/checklists/{$this->item->id}", ['item_descripcion' => 'Otra'])->assertOk();

        $this->assertDatabaseHas('work_flow_tareas', ['id' => $this->plantilla->id, 'estado' => 'en_curso']);
        $this->assertDatabaseHas('work_flow_tarea_checklists', ['id' => $this->item->id, 'item_descripcion' => 'Otra']);
    }
}
