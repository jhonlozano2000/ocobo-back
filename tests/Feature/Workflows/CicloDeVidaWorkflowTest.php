<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-22: el estado del flujo se editaba libre (archivado → activo, activo → borrador, ...).
 */
class CicloDeVidaWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Workflows -> Editar');
    }

    private function workflow(string $estado): Workflow
    {
        $workflow = Workflow::create(['nombre' => "M19-22 {$estado}", 'estado' => $estado, 'creador_user_id' => $this->usuario->id]);
        $inicio = $workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
        $fin = $workflow->nodos()->create(['tipo' => 'fin', 'titulo' => 'Fin', 'posicion_x' => 0, 'posicion_y' => 0]);
        $workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $fin->id]);

        return $workflow;
    }

    public function test_transiciones_no_permitidas_dan_422(): void
    {
        foreach ([['archivado', 'activo'], ['activo', 'borrador'], ['archivado', 'borrador']] as [$desde, $hasta]) {
            $workflow = $this->workflow($desde);

            $this->actingAs($this->usuario)->patchJson("/api/workflows/workflows/{$workflow->id}/estado", ['estado' => $hasta])
                ->assertStatus(422);
            $this->actingAs($this->usuario)->putJson("/api/workflows/workflows/{$workflow->id}", ['estado' => $hasta])
                ->assertStatus(422);
            $this->assertSame($desde, $workflow->fresh()->estado);
        }
    }

    public function test_ciclo_permitido_y_mismo_estado_en_la_edicion(): void
    {
        $workflow = $this->workflow('borrador');

        foreach (['activo', 'inactivo', 'borrador', 'archivado', 'inactivo'] as $estado) {
            $this->actingAs($this->usuario)->patchJson("/api/workflows/workflows/{$workflow->id}/estado", ['estado' => $estado])
                ->assertOk();
            $this->assertSame($estado, $workflow->fresh()->estado);
        }

        // El formulario reenvía el estado actual junto al resto de campos
        $this->actingAs($this->usuario)->putJson("/api/workflows/workflows/{$workflow->id}", ['nombre' => 'Renombrado', 'estado' => 'inactivo'])
            ->assertOk();
        $this->assertSame('Renombrado', $workflow->fresh()->nombre);
    }
}
