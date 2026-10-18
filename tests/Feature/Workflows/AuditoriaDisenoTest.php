<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowAudit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-15: el diseño no dejaba rastro de qué cambió (el canvas solo contaba nodos; el CRUD de nodos nada).
 */
class AuditoriaDisenoTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo('Workflows -> Workflows -> Editar');
        $this->workflow = Workflow::create(['nombre' => 'M19-15', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id]);
    }

    private function ultima(string $accion): array
    {
        $audit = WorkflowAudit::where('workflow_id', $this->workflow->id)->where('action', $accion)->latest('id')->firstOrFail();
        $this->assertSame($this->creador->id, $audit->user_id);

        return $audit->payload_json;
    }

    public function test_el_canvas_registra_nodos_creados_modificados_eliminados_y_conexiones(): void
    {
        $nodo = fn (string $id, string $tipo, string $titulo) => [
            'client_id' => $id, 'tipo' => $tipo, 'titulo' => $titulo, 'posicion_x' => 0, 'posicion_y' => 0,
        ];
        $ruta = "/api/workflows/workflows/{$this->workflow->id}/canvas";

        $this->actingAs($this->creador)->postJson($ruta, [
            'nodos' => [$nodo('a', 'inicio', 'Inicio'), $nodo('b', 'tarea', 'Revisar'), $nodo('c', 'fin', 'Fin')],
            'conexiones' => [['nodo_origen_id' => 'a', 'nodo_destino_id' => 'b'], ['nodo_origen_id' => 'b', 'nodo_destino_id' => 'c']],
        ])->assertOk();
        $this->assertCount(3, $this->ultima('workflow.canvas_guardado')['nodos']['creados']);

        $ids = $this->workflow->nodos()->pluck('id', 'titulo');
        $this->actingAs($this->creador)->postJson($ruta, [
            'nodos' => [$nodo("nodo_{$ids['Inicio']}", 'inicio', 'Inicio'), $nodo("nodo_{$ids['Fin']}", 'fin', 'Cierre')],
            'conexiones' => [['nodo_origen_id' => "nodo_{$ids['Inicio']}", 'nodo_destino_id' => "nodo_{$ids['Fin']}"]],
        ])->assertOk();

        $payload = $this->ultima('workflow.canvas_guardado');
        $this->assertEquals([['id' => $ids['Fin'], 'titulo' => 'Cierre', 'campos' => ['titulo']]], $payload['nodos']['modificados']);
        $this->assertEquals([['id' => $ids['Revisar'], 'titulo' => 'Revisar', 'tipo' => 'tarea']], $payload['nodos']['eliminados']);
        $this->assertSame([], $payload['nodos']['creados']);
        $this->assertSame(["{$ids['Inicio']}→{$ids['Revisar']}", "{$ids['Revisar']}→{$ids['Fin']}"], $payload['conexiones']['antes']);
        $this->assertSame(["{$ids['Inicio']}→{$ids['Fin']}"], $payload['conexiones']['despues']);
    }

    public function test_el_crud_de_nodos_queda_auditado(): void
    {
        $ruta = "/api/workflows/workflows/{$this->workflow->id}/nodos";
        $id = $this->actingAs($this->creador)->postJson($ruta, ['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0])
            ->assertSuccessful()->json('data.id');
        $this->assertSame('Revisar', $this->ultima('nodo.creado')['titulo']);

        $this->actingAs($this->creador)->putJson("{$ruta}/{$id}", ['titulo' => 'Aprobar'])->assertOk();
        $this->assertSame(['titulo' => 'Aprobar'], $this->ultima('nodo.actualizado')['cambios']);

        $this->actingAs($this->creador)->deleteJson("{$ruta}/{$id}")->assertOk();
        $this->assertSame($id, $this->ultima('nodo.eliminado')['nodo_id']);
    }
}
