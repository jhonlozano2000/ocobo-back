<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-21: eliminar un flujo no miraba sus instancias; las en curso quedaban huérfanas.
 */
class EliminarWorkflowConInstanciasTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Workflows -> Eliminar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Workflows -> Eliminar');
    }

    public function test_un_flujo_con_instancias_no_se_elimina(): void
    {
        $workflow = Workflow::create(['nombre' => 'M19-21 con historial', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
        WorkflowInstancia::create(['workflow_id' => $workflow->id, 'estado' => 'completada', 'usuario_ejecuta_id' => $this->usuario->id, 'fecha_inicio' => now()]);

        $this->actingAs($this->usuario)->deleteJson("/api/workflows/workflows/{$workflow->id}")
            ->assertStatus(422);
        $this->assertNotSoftDeleted('workflows', ['id' => $workflow->id]);
    }

    public function test_un_flujo_sin_instancias_se_elimina(): void
    {
        $workflow = Workflow::create(['nombre' => 'M19-21 vacío', 'estado' => 'borrador', 'creador_user_id' => $this->usuario->id]);

        $this->actingAs($this->usuario)->deleteJson("/api/workflows/workflows/{$workflow->id}")
            ->assertOk();
        $this->assertSoftDeleted('workflows', ['id' => $workflow->id]);
    }
}
