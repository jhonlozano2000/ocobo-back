<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M19-13: no había tarea programada; una tarea vencida seguía "en curso" hasta que alguien
 * abría el listado, y nadie recibía la alerta.
 */
class MarcarTareasVencidasCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_el_comando_vence_las_tareas_y_audita_como_sistema(): void
    {
        $nullable = DB::selectOne(
            "SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_audits' AND COLUMN_NAME = 'user_id'"
        );
        if (($nullable->n ?? 'NO') !== 'YES') {
            $this->markTestSkipped('BD de pruebas sin la migración make_user_id_nullable_on_workflow_audits.');
        }

        $responsable = User::factory()->create(['estado' => 1]);
        $workflow = Workflow::create(['nombre' => 'Barrido M19-13', 'estado' => 'activo', 'creador_user_id' => $responsable->id]);
        $vencida = Tarea::create(['workflow_id' => $workflow->id, 'nombre' => 'Atrasada', 'estado' => 'en_curso', 'fecha_limite' => now()->subHour()]);
        $vencida->responsables()->attach($responsable->id);
        $aTiempo = Tarea::create(['workflow_id' => $workflow->id, 'nombre' => 'A tiempo', 'estado' => 'pendiente', 'fecha_limite' => now()->addDay()]);

        $this->artisan('workflows:marcar-tareas-vencidas')->assertSuccessful();

        $this->assertSame('vencida', $vencida->fresh()->estado);
        $this->assertSame('pendiente', $aTiempo->fresh()->estado);
        $this->assertDatabaseHas('workflow_audits', ['workflow_id' => $workflow->id, 'action' => 'tarea.vencida', 'user_id' => null]);
        $this->assertDatabaseHas('notifications', ['user_id' => $responsable->id, 'type' => 'tarea.vencida', 'notifiable_id' => $vencida->id]);
    }

    public function test_el_comando_esta_programado(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('workflows:marcar-tareas-vencidas');
    }
}
