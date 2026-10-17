<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-09: ejecutar/detener no cruzaba la instancia con el workflow de la ruta;
 * con permiso sobre cualquier workflow se operaba la instancia de otro.
 */
class InstanciaDeOtroWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    private function workflowActivo(string $nombre): Workflow
    {
        $workflow = Workflow::create(['nombre' => "M19-09 {$nombre}", 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
        $inicio = $workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
        $paso = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $fin = $workflow->nodos()->create(['tipo' => 'fin', 'titulo' => 'Fin', 'posicion_x' => 0, 'posicion_y' => 0]);
        $workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $paso->id]);
        $workflow->conexiones()->create(['nodo_origen_id' => $paso->id, 'nodo_destino_id' => $fin->id]);

        return $workflow;
    }

    public function test_no_se_ejecuta_ni_detiene_una_instancia_desde_otro_workflow(): void
    {
        $propio = $this->workflowActivo('propio');
        $ajeno = $this->workflowActivo('ajeno');
        $paso = $propio->nodos()->where('tipo', 'tarea')->first();

        $id = $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$propio->id}/instancias")
            ->assertCreated()->json('data.id');

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$ajeno->id}/instancias/{$id}/ejecutar", ['nodo_id' => $paso->id])
            ->assertNotFound();
        $this->actingAs($this->usuario)->patchJson("/api/workflows/workflows/{$ajeno->id}/instancias/{$id}/detener", ['estado' => 'cancelada'])
            ->assertNotFound();

        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $paso->id]);
    }
}
