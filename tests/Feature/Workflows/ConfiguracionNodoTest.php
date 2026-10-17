<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-24: la edición de nodo validaba configuracion_json como string JSON y el cast array lo
 * volvía a codificar (se guardaba "\"{...}\"" y se leía como string).
 */
class ConfiguracionNodoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_configuracion_json_se_guarda_como_objeto(): void
    {
        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $usuario = User::factory()->create(['estado' => 1]);
        $usuario->givePermissionTo('Workflows -> Workflows -> Editar');
        $workflow = Workflow::create(['nombre' => 'M19-24', 'estado' => 'borrador', 'creador_user_id' => $usuario->id]);
        $nodo = $workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $ruta = "/api/workflows/workflows/{$workflow->id}/nodos/{$nodo->id}";

        $this->actingAs($usuario)->putJson($ruta, ['configuracion_json' => ['plazo' => 3]])
            ->assertOk()->assertJsonPath('data.configuracion_json.plazo', 3);
        $this->assertSame(['plazo' => 3], $nodo->fresh()->configuracion_json);

        $this->actingAs($usuario)->putJson($ruta, ['configuracion_json' => '{"plazo":3}'])
            ->assertStatus(422);
    }
}
