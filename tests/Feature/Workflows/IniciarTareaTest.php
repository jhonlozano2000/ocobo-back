<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Iniciar una tarea exigía ser propietario; como completar exige en_curso, el responsable
 * no podía iniciar ni terminar su propia tarea.
 */
class IniciarTareaTest extends TestCase
{
    use DatabaseTransactions;

    private User $responsable;

    private User $ajeno;

    private Tarea $tarea;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Tareas -> Editar', 'web');
        [$propietario, $this->responsable, $this->ajeno] = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach ([$propietario, $this->responsable, $this->ajeno] as $usuario) {
            $usuario->givePermissionTo('Workflows -> Tareas -> Editar');
        }

        $workflow = Workflow::create(['nombre' => 'Iniciar tarea', 'estado' => 'activo', 'creador_user_id' => $propietario->id]);
        $this->tarea = Tarea::create(['workflow_id' => $workflow->id, 'nombre' => 'Revisar', 'estado' => 'pendiente']);
        $this->tarea->propietarios()->attach($propietario->id);
        $this->tarea->responsables()->attach($this->responsable->id);
    }

    public function test_el_responsable_inicia_y_completa_su_tarea(): void
    {
        $this->actingAs($this->responsable)->postJson("/api/workflows/tareas/{$this->tarea->id}/iniciar")->assertOk();
        $this->actingAs($this->responsable)->postJson("/api/workflows/tareas/{$this->tarea->id}/completar")->assertOk();

        $this->assertSame('completada', $this->tarea->fresh()->estado);
    }

    public function test_un_usuario_ajeno_no_inicia_la_tarea(): void
    {
        $this->actingAs($this->ajeno)->postJson("/api/workflows/tareas/{$this->tarea->id}/iniciar")->assertForbidden();
        $this->assertSame('pendiente', $this->tarea->fresh()->estado);
    }
}
