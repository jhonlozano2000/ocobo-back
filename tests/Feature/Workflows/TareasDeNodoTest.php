<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-17: las tareas de nodo (WorkFlowTarea) nunca llegaban a "Mis tareas". Ahora son plantillas:
 * al entrar la instancia en el nodo se crea una Tarea por plantilla, con su checklist.
 */
class TareasDeNodoTest extends TestCase
{
    use DatabaseTransactions;

    private User $ejecutor;

    private User $revisor;

    private Workflow $workflow;

    private WorkflowNodo $revision;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_17_120000 (instancia/nodo en workflow_tareas).');
        }

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->ejecutor = User::factory()->create(['estado' => 1]);
        $this->ejecutor->givePermissionTo('Workflows -> Instancias -> Ejecutar');
        $this->revisor = User::factory()->create(['estado' => 1]);

        $this->workflow = Workflow::create(['nombre' => 'M19-17', 'estado' => 'activo', 'creador_user_id' => $this->ejecutor->id]);
        $nodo = fn (string $tipo, array $extra = []) => $this->workflow->nodos()->create(
            ['tipo' => $tipo, 'titulo' => ucfirst($tipo), 'posicion_x' => 0, 'posicion_y' => 0] + $extra
        );
        $inicio = $nodo('inicio');
        $this->revision = $nodo('tarea', ['responsable_usuario_id' => $this->revisor->id, 'tiempo_limite_horas' => 24]);
        $fin = $nodo('fin');
        $this->workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $this->revision->id]);
        $this->workflow->conexiones()->create(['nodo_origen_id' => $this->revision->id, 'nodo_destino_id' => $fin->id]);

        $plantilla = $this->revision->tareas()->create(['titulo' => 'Revisar soporte', 'instrucciones' => 'Verificar firmas', 'orden' => 1]);
        $plantilla->checklists()->create(['item_descripcion' => 'Firma del jefe', 'orden' => 1]);
        $this->revision->tareas()->create([
            'titulo' => 'Archivar', 'orden' => 2, 'responsable_usuario_id' => $this->ejecutor->id, 'tiempo_limite_horas' => 2,
        ]);
    }

    private function iniciar(): int
    {
        return $this->actingAs($this->ejecutor)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias")
            ->assertCreated()
            ->json('data.id');
    }

    public function test_al_entrar_en_el_nodo_se_crean_las_tareas_de_sus_plantillas(): void
    {
        $instanciaId = $this->iniciar();

        $tareas = Tarea::with(['responsables', 'propietarios', 'checklists'])
            ->where('instancia_id', $instanciaId)->orderBy('id')->get();
        $this->assertCount(2, $tareas);

        [$revisar, $archivar] = $tareas;
        $this->assertSame('Revisar soporte', $revisar->nombre);
        $this->assertSame('Verificar firmas', $revisar->descripcion);
        $this->assertSame($this->revision->id, $revisar->nodo_id);
        $this->assertSame('pendiente', $revisar->estado);
        // Sin responsable propio hereda el del nodo; el plazo de la plantilla manda sobre el del nodo
        $this->assertSame([$this->revisor->id], $revisar->responsables->pluck('id')->all());
        $this->assertSame([$this->ejecutor->id], $revisar->propietarios->pluck('id')->all());
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $revisar->fecha_limite->timestamp, 60);
        $this->assertSame(['Firma del jefe'], $revisar->checklists->pluck('item_descripcion')->all());
        $this->assertSame([$this->ejecutor->id], $archivar->responsables->pluck('id')->all());
        $this->assertEqualsWithDelta(now()->addHours(2)->timestamp, $archivar->fecha_limite->timestamp, 60);

        $this->assertDatabaseHas('notifications', ['user_id' => $this->revisor->id, 'notifiable_id' => $revisar->id, 'type' => 'tarea.asignada']);
        // Las plantillas no se tocan
        $this->assertSame(2, $this->revision->tareas()->whereNull('instancia_id')->count());
    }

    public function test_detener_la_instancia_cancela_sus_tareas_abiertas(): void
    {
        $instanciaId = $this->iniciar();
        Tarea::where('instancia_id', $instanciaId)->where('nombre', 'Archivar')->update(['estado' => 'completada']);

        $this->actingAs($this->ejecutor)
            ->patchJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$instanciaId}/detener")
            ->assertOk();

        $this->assertEquals(
            ['Revisar soporte' => 'cancelada', 'Archivar' => 'completada'],
            Tarea::where('instancia_id', $instanciaId)->pluck('estado', 'nombre')->all()
        );
    }
}
