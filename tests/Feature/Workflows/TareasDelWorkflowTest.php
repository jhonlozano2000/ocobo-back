<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-03: GET /api/workflows/{workflow}/tareas devolvía todas las tareas de cualquier workflow
 * a quien tuviera el permiso Listar, sin mirar propietario ni responsable.
 */
class TareasDelWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private Workflow $workflow;

    private Tarea $suya;

    private Tarea $ajena;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo(['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar']);
        $this->workflow = Workflow::create([
            'nombre' => 'Tareas M19-03', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id,
        ]);
        $this->suya = Tarea::create(['workflow_id' => $this->workflow->id, 'nombre' => 'Suya', 'estado' => 'pendiente']);
        $this->ajena = Tarea::create(['workflow_id' => $this->workflow->id, 'nombre' => 'Ajena', 'estado' => 'pendiente']);
    }

    private function url(): string
    {
        return "/api/workflows/{$this->workflow->id}/tareas";
    }

    public function test_el_creador_ve_todas_las_tareas(): void
    {
        $this->actingAs($this->creador)->getJson($this->url())->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_otro_usuario_solo_ve_las_tareas_donde_participa(): void
    {
        $otro = User::factory()->create(['estado' => 1]);
        $otro->givePermissionTo(['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar']);
        $this->suya->responsables()->attach($otro->id);

        $this->actingAs($otro)->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->suya->id);
    }

    public function test_un_workflow_inexistente_da_404(): void
    {
        $this->actingAs($this->creador)->getJson('/api/workflows/999999999/tareas')->assertNotFound();
    }
}
