<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Services\Workflows\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * El detalle del flujo trae cuántas instancias hay en curso para que el builder avise de entrada
 * que no se puede guardar el grafo (el canvas responde 422 mientras haya alguna).
 */
class InstanciasEnCursoDetalleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_el_detalle_cuenta_solo_las_instancias_en_curso(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $workflow = Workflow::create(['nombre' => 'Detalle', 'estado' => 'activo', 'creador_user_id' => $user->id]);

        foreach (['en_curso', 'en_curso', 'completada', 'detenida'] as $estado) {
            WorkflowInstancia::create([
                'workflow_id' => $workflow->id, 'estado' => $estado,
                'usuario_ejecuta_id' => $user->id, 'fecha_inicio' => now(),
            ]);
        }

        $detalle = app(WorkflowService::class)->obtenerConRelaciones($workflow->id);

        $this->assertSame(2, (int) $detalle->instancias_en_curso_count);
    }
}
