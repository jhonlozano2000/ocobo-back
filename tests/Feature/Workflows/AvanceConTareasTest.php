<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-19: las tareas no condicionaban el avance: se ejecutaba el nodo con tareas abiertas y
 * completarlas todas no lo avanzaba.
 */
class AvanceConTareasTest extends TestCase
{
    use DatabaseTransactions;

    private User $ejecutor;

    private User $revisor;

    private Workflow $workflow;

    private WorkflowNodo $revision;

    private WorkflowNodo $aprobacion;

    private int $instanciaId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_17_120000 (instancia/nodo en workflow_tareas).');
        }

        foreach (['Workflows -> Instancias -> Ejecutar', 'Workflows -> Tareas -> Editar', 'Workflows -> Tareas -> Eliminar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->ejecutor = User::factory()->create(['estado' => 1]);
        $this->ejecutor->givePermissionTo('Workflows -> Instancias -> Ejecutar', 'Workflows -> Tareas -> Editar', 'Workflows -> Tareas -> Eliminar');
        $this->revisor = User::factory()->create(['estado' => 1]);
        $this->revisor->givePermissionTo('Workflows -> Tareas -> Editar');

        $this->workflow = Workflow::create(['nombre' => 'M19-19', 'estado' => 'activo', 'creador_user_id' => $this->ejecutor->id]);
        $nodo = fn (string $tipo, string $titulo) => $this->workflow->nodos()->create(
            ['tipo' => $tipo, 'titulo' => $titulo, 'posicion_x' => 0, 'posicion_y' => 0, 'responsable_usuario_id' => $this->revisor->id]
        );
        $inicio = $nodo('inicio', 'Inicio');
        $this->revision = $nodo('tarea', 'Revisión');
        $this->aprobacion = $nodo('tarea', 'Aprobación');
        $fin = $nodo('fin', 'Fin');
        foreach ([[$inicio, $this->revision], [$this->revision, $this->aprobacion], [$this->aprobacion, $fin]] as [$o, $d]) {
            $this->workflow->conexiones()->create(['nodo_origen_id' => $o->id, 'nodo_destino_id' => $d->id]);
        }
        $this->revision->tareas()->create(['titulo' => 'Revisar soporte', 'orden' => 1]);
        $this->revision->tareas()->create(['titulo' => 'Revisar firmas', 'orden' => 2]);

        $this->instanciaId = $this->actingAs($this->ejecutor)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias")
            ->assertCreated()
            ->json('data.id');
    }

    private function tarea(string $nombre): Tarea
    {
        return Tarea::where('instancia_id', $this->instanciaId)->where('nombre', $nombre)->firstOrFail();
    }

    private function completar(string $nombre): void
    {
        $tarea = $this->tarea($nombre);
        $this->actingAs($this->revisor)->postJson("/api/workflows/tareas/{$tarea->id}/iniciar")->assertOk();
        $this->actingAs($this->revisor)->postJson("/api/workflows/tareas/{$tarea->id}/completar")->assertOk();
    }

    private function nodoActual(): ?int
    {
        return WorkflowInstancia::find($this->instanciaId)->nodo_actual_id;
    }

    public function test_no_se_ejecuta_un_nodo_con_tareas_abiertas(): void
    {
        $this->actingAs($this->ejecutor)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$this->instanciaId}/ejecutar", ['nodo_id' => $this->revision->id])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El nodo tiene 2 tarea(s) sin completar']);
        $this->assertSame($this->revision->id, $this->nodoActual());
    }

    public function test_completar_la_ultima_tarea_avanza_el_nodo(): void
    {
        $this->completar('Revisar soporte');
        $this->assertSame($this->revision->id, $this->nodoActual());

        $this->completar('Revisar firmas');
        $this->assertSame($this->aprobacion->id, $this->nodoActual());
        $this->assertDatabaseHas('workflow_nodos_instancia', ['instancia_id' => $this->instanciaId, 'nodo_id' => $this->revision->id, 'estado' => 'completado']);
    }

    public function test_cancelar_no_avanza_pero_desbloquea_el_avance_manual(): void
    {
        $this->completar('Revisar soporte');
        $this->actingAs($this->ejecutor)->postJson('/api/workflows/tareas/'.$this->tarea('Revisar firmas')->id.'/cancelar')->assertOk();
        $this->assertSame($this->revision->id, $this->nodoActual());

        $this->actingAs($this->ejecutor)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$this->instanciaId}/ejecutar", ['nodo_id' => $this->revision->id])
            ->assertOk();
        $this->assertSame($this->aprobacion->id, $this->nodoActual());
    }
}
