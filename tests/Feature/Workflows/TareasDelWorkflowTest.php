<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El tablero muestra tareas del flujo a sus participantes; las acciones exigen responsabilidad.
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

    public function test_participante_ve_tablero_pero_solo_tramita_su_tarea(): void
    {
        Permission::findOrCreate('Workflows -> Tareas -> Editar', 'web');
        $otro = User::factory()->create(['estado' => 1]);
        $otro->givePermissionTo(['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar', 'Workflows -> Tareas -> Editar']);
        $this->suya->responsables()->attach($otro->id);

        $this->actingAs($otro)->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $this->suya->id, 'nombre' => 'Suya'])
            ->assertJsonFragment(['id' => $this->ajena->id, 'nombre' => 'Ajena']);

        $this->postJson("/api/workflows/tareas/{$this->ajena->id}/iniciar")->assertForbidden();
        $this->assertSame('pendiente', $this->ajena->fresh()->estado);
        $this->postJson("/api/workflows/tareas/{$this->suya->id}/iniciar")->assertOk();
        $this->assertSame('en_curso', $this->suya->fresh()->estado);
    }

    public function test_usuario_sin_pertenencia_no_ve_el_tablero(): void
    {
        $otro = User::factory()->create(['estado' => 1]);
        $otro->givePermissionTo(['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar']);
        $this->actingAs($otro)->getJson($this->url())->assertForbidden();
    }

    public function test_un_workflow_inexistente_da_404(): void
    {
        $this->actingAs($this->creador)->getJson('/api/workflows/999999999/tareas')->assertNotFound();
    }
}
