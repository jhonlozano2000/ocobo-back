<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\TareaChecklist;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-34: el responsable debe poder marcar los ítems del checklist de su tarea; las rutas solo
 * exigían el permiso plano, así que cualquiera con Editar alteraba checklists de tareas ajenas.
 */
class ChecklistTareaTest extends TestCase
{
    use DatabaseTransactions;

    private User $propietario;

    private User $responsable;

    private User $ajeno;

    private Tarea $tarea;

    private TareaChecklist $item;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Tareas -> Editar', 'web');
        [$this->propietario, $this->responsable, $this->ajeno] = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach ([$this->propietario, $this->responsable, $this->ajeno] as $usuario) {
            $usuario->givePermissionTo('Workflows -> Tareas -> Editar');
        }

        $workflow = Workflow::create(['nombre' => 'M19-34', 'estado' => 'activo', 'creador_user_id' => $this->propietario->id]);
        $this->tarea = Tarea::create(['workflow_id' => $workflow->id, 'nombre' => 'Revisar', 'estado' => 'en_curso']);
        $this->tarea->propietarios()->attach($this->propietario->id);
        $this->tarea->responsables()->attach($this->responsable->id);
        $this->item = $this->tarea->checklists()->create(['item_descripcion' => 'Leer el anexo', 'esta_completado' => false, 'orden' => 0]);
    }

    private function url(): string
    {
        return "/api/workflows/tareas/{$this->tarea->id}/checklists/{$this->item->id}";
    }

    public function test_el_responsable_marca_un_item(): void
    {
        $this->actingAs($this->responsable)->putJson($this->url(), ['esta_completado' => true])->assertOk();

        $this->assertDatabaseHas('tarea_checklists', ['id' => $this->item->id, 'esta_completado' => true, 'item_descripcion' => 'Leer el anexo']);
    }

    public function test_el_responsable_no_reescribe_el_checklist(): void
    {
        $this->actingAs($this->responsable);

        $this->putJson($this->url(), ['item_descripcion' => 'Otra cosa'])->assertForbidden();
        $this->deleteJson($this->url())->assertForbidden();
        $this->postJson("/api/workflows/tareas/{$this->tarea->id}/checklists", ['item_descripcion' => 'Nuevo'])->assertForbidden();
    }

    public function test_un_usuario_ajeno_no_toca_el_checklist(): void
    {
        $this->actingAs($this->ajeno)->putJson($this->url(), ['esta_completado' => true])->assertForbidden();

        $this->assertDatabaseHas('tarea_checklists', ['id' => $this->item->id, 'esta_completado' => false]);
    }

    public function test_el_propietario_edita_el_checklist(): void
    {
        $this->actingAs($this->propietario)->putJson($this->url(), ['item_descripcion' => 'Leer el anexo 2'])->assertOk();
    }
}
