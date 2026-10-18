<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-20: un nodo sin salida daba la instancia por completada en vez de señalar el error de diseño.
 * (Flujos activados antes de la validación de grafo de M19-18.)
 */
class NodoSinSalidaTest extends TestCase
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

    private function workflowActivo(): Workflow
    {
        return Workflow::create(['nombre' => 'M19-20', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
    }

    public function test_un_inicio_sin_salida_no_crea_una_instancia_completada(): void
    {
        $workflow = $this->workflowActivo();
        $workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias")
            ->assertStatus(422);
        $this->assertDatabaseMissing('workflow_instancias', ['workflow_id' => $workflow->id]);
    }

    public function test_un_paso_sin_salida_no_completa_la_instancia(): void
    {
        if (! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_17_120000 (ejecutarNodo revisa las tareas del nodo).');
        }
        $workflow = $this->workflowActivo();
        $inicio = $workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
        $paso = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Callejón', 'posicion_x' => 0, 'posicion_y' => 0]);
        $workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $paso->id]);

        $id = $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias")
            ->assertCreated()->json('data.id');

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias/{$id}/ejecutar", ['nodo_id' => $paso->id])
            ->assertStatus(422);
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $paso->id]);
        $this->assertDatabaseHas('workflow_nodos_instancia', ['instancia_id' => $id, 'nodo_id' => $paso->id, 'estado' => 'en_curso']);
    }
}
