<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkFlowTarea;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El checklist de una tarea de nodo daba 500 en todas sus rutas: Laravel pasa los parámetros de
 * la ruta por posición y {workflow} llegaba al argumento $tarea (TypeError).
 */
class ChecklistPlantillaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_el_creador_gestiona_el_checklist_de_la_plantilla(): void
    {
        $permisos = ['Workflows -> Tareas -> Listar', 'Workflows -> Tareas -> Editar', 'Workflows -> Tareas -> Eliminar', 'Workflows -> Workflows -> Editar'];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $creador = User::factory()->create(['estado' => 1]);
        $creador->givePermissionTo($permisos);

        $workflow = Workflow::create(['nombre' => 'Checklist plantilla', 'estado' => 'borrador', 'creador_user_id' => $creador->id]);
        $nodo = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $plantilla = WorkFlowTarea::create(['nodo_id' => $nodo->id, 'titulo' => 'Revisar anexo', 'estado' => 'pendiente', 'orden' => 0]);
        $url = "/api/workflows/workflows/{$workflow->id}/nodos/{$nodo->id}/tareas/{$plantilla->id}/checklists";

        $this->actingAs($creador);
        $a = $this->postJson($url, ['item_descripcion' => 'Leer'])->assertCreated()->json('data.id');
        $b = $this->postJson($url, ['item_descripcion' => 'Firmar'])->assertCreated()->json('data.id');
        $this->putJson("{$url}/{$a}", ['item_descripcion' => 'Leer el anexo'])->assertOk()->assertJsonPath('data.item_descripcion', 'Leer el anexo');
        $this->putJson("{$url}/reordenar", ['orden' => [$b, $a]])->assertOk();
        $this->deleteJson("{$url}/{$b}")->assertOk();

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a);
    }
}
