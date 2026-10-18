<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-08: títulos y descripciones de nodos (y de sus plantillas de tarea) se guardaban con HTML;
 * las plantillas además se copian a la Tarea sin pasar por el Purifier de TareaService.
 */
class TextoPlanoDisenoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nodos_y_plantillas_se_guardan_sin_html(): void
    {
        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $usuario = User::factory()->create(['estado' => 1]);
        $usuario->givePermissionTo('Workflows -> Workflows -> Editar');
        $workflow = Workflow::create(['nombre' => 'M19-08', 'estado' => 'borrador', 'creador_user_id' => $usuario->id]);

        $id = $this->actingAs($usuario)->postJson("/api/workflows/workflows/{$workflow->id}/nodos", [
            'tipo' => 'tarea',
            'titulo' => '<img src=x onerror=alert(1)>Revisar',
            'descripcion' => '<script>alert(1)</script><b>Firmas</b>',
            'posicion_x' => 0,
            'posicion_y' => 0,
        ])->assertSuccessful()->json('data.id');

        $nodo = $workflow->nodos()->findOrFail($id);
        $this->assertSame('Revisar', $nodo->titulo);
        $this->assertSame('Firmas', $nodo->descripcion);

        $plantilla = $nodo->tareas()->create(['titulo' => '<a href="javascript:x">Archivar</a>', 'instrucciones' => '<iframe></iframe>Paso 1']);
        $this->assertSame('Archivar', $plantilla->fresh()->titulo);
        $this->assertSame('Paso 1', $plantilla->fresh()->instrucciones);
    }
}
