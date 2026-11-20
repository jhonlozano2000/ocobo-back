<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkFlowTareaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** El editor reutiliza el listado: debe recibir la lista guardada, incluso tras reordenar. */
class ChecklistTareaLecturaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listado_conserva_checklist_creado_actualizado_y_vaciado(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $this->actingAs($user);
        $workflow = Workflow::create([
            'nombre' => 'Persistencia de checklist',
            'estado' => 'borrador',
            'creador_user_id' => $user->id,
        ]);
        $nodo = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisión', 'orden_ejecucion' => 0]);
        $service = app(WorkFlowTareaService::class);
        $tarea = $service->crear($nodo->id, [
            'titulo' => 'Verificar documentos',
            'checklists' => [['item_descripcion' => 'Revisar anexos']],
        ]);

        $fila = $service->listar($nodo->id)->firstWhere('id', $tarea->id)->toArray();
        $this->assertSame('Revisar anexos', $fila['checklists'][0]['item_descripcion']);

        $service->actualizar($tarea->id, ['checklists' => [
            ['id' => $fila['checklists'][0]['id'], 'item_descripcion' => 'Revisar identificación'],
            ['item_descripcion' => 'Validar firma'],
        ]]);
        $fila = $service->listar($nodo->id)->firstWhere('id', $tarea->id)->toArray();
        $this->assertSame(['Revisar identificación', 'Validar firma'], array_column($fila['checklists'], 'item_descripcion'));

        $reordenada = $service->reordenar($nodo->id, [$tarea->id])->first()->toArray();
        $this->assertSame($fila['checklists'], $reordenada['checklists']);

        $service->actualizar($tarea->id, ['checklists' => []]);
        $fila = $service->listar($nodo->id)->firstWhere('id', $tarea->id)->toArray();
        $this->assertSame([], $fila['checklists']);
        $this->assertSame('Verificar documentos', $fila['titulo']);
    }
}
