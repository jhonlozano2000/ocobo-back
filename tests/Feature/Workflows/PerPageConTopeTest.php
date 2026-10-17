<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-07: per_page sin tope en el listado de workflows y de instancias (per_page=100000 volcaba la tabla).
 */
class PerPageConTopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_per_page_se_limita_a_100_y_minimo_1(): void
    {
        foreach (['Workflows -> Workflows -> Listar', 'Workflows -> Instancias -> Consultar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $usuario = User::factory()->create(['estado' => 1]);
        $usuario->givePermissionTo('Workflows -> Workflows -> Listar', 'Workflows -> Instancias -> Consultar');
        $workflow = Workflow::create(['nombre' => 'M19-07', 'estado' => 'activo', 'creador_user_id' => $usuario->id]);

        $rutas = ['/api/workflows/workflows', "/api/workflows/workflows/{$workflow->id}/instancias", '/api/workflows/mis-instancias'];
        foreach ($rutas as $ruta) {
            $this->actingAs($usuario)->getJson("{$ruta}?per_page=100000")
                ->assertOk()->assertJsonPath('pagination.per_page', 100);
            $this->actingAs($usuario)->getJson("{$ruta}?per_page=-5")
                ->assertOk()->assertJsonPath('pagination.per_page', 1);
        }
    }
}
