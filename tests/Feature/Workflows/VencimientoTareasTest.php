<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\TareaService;
use App\Services\Workflows\WorkflowEstadoMachine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M19-12: la máquina de estados no permitía pendiente→vencida y el barrido solo miraba en_curso,
 * así que una tarea nunca iniciada no vencía jamás.
 */
class VencimientoTareasTest extends TestCase
{
    use DatabaseTransactions;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $creador = User::factory()->create(['estado' => 1]);
        // El barrido corre dentro de una petición autenticada (index/mis-tareas) y audita con ese usuario
        $this->actingAs($creador);
        $this->workflow = Workflow::create([
            'nombre' => 'Vencimientos M19-12', 'estado' => 'activo', 'creador_user_id' => $creador->id,
        ]);
    }

    private function tarea(string $estado, ?string $fechaLimite): Tarea
    {
        return Tarea::create([
            'workflow_id' => $this->workflow->id, 'nombre' => "Tarea {$estado}", 'estado' => $estado, 'fecha_limite' => $fechaLimite,
        ]);
    }

    public function test_pendiente_puede_transitar_a_vencida(): void
    {
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('pendiente', 'vencida'));
    }

    public function test_el_barrido_vence_pendientes_y_en_curso_pero_no_terminales_ni_a_tiempo(): void
    {
        $pendiente = $this->tarea('pendiente', now()->subDay());
        $enCurso = $this->tarea('en_curso', now()->subDay());
        $completada = $this->tarea('completada', now()->subDay());
        $aTiempo = $this->tarea('pendiente', now()->addDay());

        app(TareaService::class)->verificarVencimientosPorWorkflow($this->workflow->id);

        $this->assertSame('vencida', $pendiente->fresh()->estado);
        $this->assertSame('vencida', $enCurso->fresh()->estado);
        $this->assertSame('completada', $completada->fresh()->estado);
        $this->assertSame('pendiente', $aTiempo->fresh()->estado);
    }

    public function test_una_pendiente_vencida_se_marca_al_cargarla(): void
    {
        $pendiente = $this->tarea('pendiente', now()->subHour());

        $this->assertSame('vencida', app(TareaService::class)->verificarVencimientoAlCargar($pendiente)->estado);
    }

    /** fecha_limite llega casteada a Carbon; con strict_types la firma ?string lanzaba TypeError (500). */
    public function test_una_en_curso_vencida_se_marca_al_cargarla_sin_type_error(): void
    {
        $enCurso = $this->tarea('en_curso', now()->subHour());

        $this->assertSame('vencida', app(TareaService::class)->verificarVencimientoAlCargar($enCurso)->estado);
    }
}
