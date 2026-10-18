<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodoInstancia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-27: la condición evaluaba una condicion_json que el builder nunca escribía y el flujo seguía
 * siempre por "Sí". Ahora quien ejecuta la condición elige la rama.
 */
class CondicionManualTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_17_120000 (ejecutarNodo revisa las tareas del nodo).');
        }

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    /** inicio → ¿Aprobado? —Sí→ Archivar → fin / —No→ fin rechazo */
    private function flujo(?string $si = 'Sí', ?string $no = 'No'): array
    {
        $workflow = Workflow::create(['nombre' => 'M19-27', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
        $nodo = fn ($tipo, $titulo) => $workflow->nodos()->create(['tipo' => $tipo, 'titulo' => $titulo, 'posicion_x' => 0, 'posicion_y' => 0]);
        $inicio = $nodo('inicio', 'Inicio');
        $condicion = $nodo('condicion', '¿Aprobado?');
        $archivar = $nodo('tarea', 'Archivar');
        $fin = $nodo('fin', 'Fin');
        $rechazo = $nodo('fin', 'Rechazado');
        $conectar = fn ($o, $d, $e = null) => $workflow->conexiones()->create(['nodo_origen_id' => $o->id, 'nodo_destino_id' => $d->id, 'etiqueta' => $e]);
        $conectar($inicio, $condicion);
        $conectar($condicion, $archivar, $si);
        $conectar($condicion, $rechazo, $no);
        $conectar($archivar, $fin);

        $id = $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$workflow->id}/instancias")
            ->assertCreated()->json('data.id');

        return [$workflow, $id, $condicion, $archivar, $rechazo];
    }

    private function ejecutar(Workflow $workflow, int $id, int $nodoId, array $resultado = [])
    {
        return $this->actingAs($this->usuario)->postJson(
            "/api/workflows/workflows/{$workflow->id}/instancias/{$id}/ejecutar",
            ['nodo_id' => $nodoId, 'resultado' => $resultado]
        );
    }

    public function test_sin_decision_no_avanza_y_la_rama_no_termina_en_su_fin(): void
    {
        [$workflow, $id, $condicion, , $rechazo] = $this->flujo();

        $this->ejecutar($workflow, $id, $condicion->id)->assertStatus(422);
        $this->ejecutar($workflow, $id, $condicion->id, ['decision' => 'Tal vez'])->assertStatus(422);
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $condicion->id]);

        $this->ejecutar($workflow, $id, $condicion->id, ['decision' => 'No'])->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada', 'nodo_actual_id' => $rechazo->id]);

        $resultado = WorkflowNodoInstancia::where('instancia_id', $id)->where('nodo_id', $condicion->id)->value('resultado_json');
        $this->assertSame('No', $resultado['_evaluacion']['rama_tomada']);
        $this->assertSame($this->usuario->id, $resultado['_evaluacion']['decidido_por']);
    }

    public function test_si_sigue_por_la_rama_si(): void
    {
        [$workflow, $id, $condicion, $archivar] = $this->flujo();

        $this->ejecutar($workflow, $id, $condicion->id, ['decision' => 'Sí'])->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $archivar->id]);
    }

    public function test_condicion_antigua_sin_etiquetas_usa_primera_como_si_y_ultima_como_no(): void
    {
        [$workflow, $id, $condicion, , $rechazo] = $this->flujo(null, null);

        $this->ejecutar($workflow, $id, $condicion->id, ['decision' => 'No'])->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada', 'nodo_actual_id' => $rechazo->id]);
    }
}
