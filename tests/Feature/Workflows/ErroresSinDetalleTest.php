<?php

namespace Tests\Feature\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Models\User;
use App\Services\Workflows\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-06: los catch genéricos respondían 400 con $e->getMessage() en 'errors' (SQL, rutas internas).
 */
class ErroresSinDetalleTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
        Permission::findOrCreate('Workflows -> Workflows -> Listar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Workflows -> Listar');
    }

    public function test_un_error_interno_es_500_sin_detalle(): void
    {
        $this->mock(WorkflowService::class, fn (MockInterface $m) => $m->shouldReceive('listar')
            ->andThrow(new RuntimeException('SQLSTATE[42S22]: Column not found: tabla_secreta')));

        $respuesta = $this->actingAs($this->usuario)->getJson('/api/workflows/workflows')->assertStatus(500);

        $this->assertStringNotContainsString('tabla_secreta', $respuesta->getContent());
        $respuesta->assertJsonPath('message', 'Error al obtener flujos');
    }

    public function test_una_excepcion_de_dominio_conserva_mensaje_y_codigo(): void
    {
        $this->mock(WorkflowService::class, fn (MockInterface $m) => $m->shouldReceive('listar')
            ->andThrow(new StateTransitionException('No se puede pasar de completada a pendiente')));

        $this->actingAs($this->usuario)->getJson('/api/workflows/workflows')
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede pasar de completada a pendiente');
    }
}
