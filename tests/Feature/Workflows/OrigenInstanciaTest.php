<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-30: una instancia se inicia desde un radicado recibido o una PQRS visible para el usuario,
 * y queda ligada al radicado recibido (el de la PQRS) para archivar sus documentos.
 */
class OrigenInstanciaTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_instancias', 'radicado_id') || ! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_17_120000 y 2026_10_18_110000.');
        }

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Instancias -> Ejecutar');

        $this->workflow = Workflow::create(['nombre' => 'M19-30 origen', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
        $inicio = $this->workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
        $paso = $this->workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $this->workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $paso->id]);
    }

    private function radicado(?int $creador): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M1930-'.uniqid(),
            'medio_recep_id' => 1,
            'usuario_crea' => $creador,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function iniciar(array $origen)
    {
        return $this->actingAs($this->usuario)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias", $origen);
    }

    public function test_inicia_desde_un_radicado_visible_y_queda_ligada(): void
    {
        $radicadoId = $this->radicado($this->usuario->id);

        $data = $this->iniciar(['origen_tipo' => 'radicado_recibido', 'origen_id' => $radicadoId])
            ->assertCreated()->json('data');

        $this->assertSame('radicado_recibido', $data['origen_tipo']);
        $this->assertSame($radicadoId, $data['radicado_id']);
        $this->assertNotNull($data['radicado']['num_radicado']);
    }

    public function test_rechaza_un_radicado_sin_acceso_sin_crear_la_instancia(): void
    {
        $otro = User::factory()->create(['estado' => 1]);
        $radicadoId = $this->radicado($otro->id);

        $this->iniciar(['origen_tipo' => 'radicado_recibido', 'origen_id' => $radicadoId])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El radicado no existe o no tiene acceso a él');

        $this->assertSame(0, WorkflowInstancia::where('workflow_id', $this->workflow->id)->count());
    }

    public function test_la_pqrs_liga_la_instancia_a_su_radicado(): void
    {
        if (! Schema::hasView('ventanilla_pqrs_view')) {
            $this->markTestSkipped('Requiere la vista ventanilla_pqrs_view.');
        }
        $radicadoId = $this->radicado($this->usuario->id);
        Schema::disableForeignKeyConstraints();
        $pqrsId = DB::table('ventanilla_pqrs')->insertGetId([
            'tipo_pqrs_id' => 1,
            'fecha_vencimiento' => '2026-11-30',
            'ventanilla_radica_reci_id' => $radicadoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $data = $this->iniciar(['origen_tipo' => 'pqrs', 'origen_id' => $pqrsId])
            ->assertCreated()->json('data');

        $this->assertSame('pqrs', $data['origen_tipo']);
        $this->assertSame($pqrsId, $data['origen_id']);
        $this->assertSame($radicadoId, $data['radicado_id']);
    }

    public function test_solo_acepta_radicados_recibidos_y_pqrs(): void
    {
        $this->iniciar(['origen_tipo' => 'radicado_enviado', 'origen_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('origen_tipo');

        $this->iniciar(['origen_tipo' => 'radicado_recibido'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('origen_id');
    }

    public function test_sin_origen_sigue_funcionando(): void
    {
        $data = $this->iniciar([])->assertCreated()->json('data');

        $this->assertNull($data['origen_tipo']);
        $this->assertNull($data['radicado_id']);
    }
}
