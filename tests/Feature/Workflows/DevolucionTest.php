<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-28: no había devolución ni reproceso; un paso mal hecho obligaba a cancelar la instancia.
 */
class DevolucionTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $tipo = DB::selectOne("SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_nodos' AND COLUMN_NAME = 'tipo'")?->t;
        if (! Schema::hasColumn('workflow_tareas', 'instancia_id') || ! str_contains((string) $tipo, 'paralelo')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_17_120000 y 2026_10_18_100000.');
        }

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Instancias -> Ejecutar');
        $this->workflow = Workflow::create(['nombre' => 'M19-28 devolución', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
    }

    private function nodo(string $tipo, string $titulo): WorkflowNodo
    {
        return $this->workflow->nodos()->create(['tipo' => $tipo, 'titulo' => $titulo, 'posicion_x' => 0, 'posicion_y' => 0]);
    }

    private function cadena(WorkflowNodo ...$nodos): void
    {
        foreach (array_slice($nodos, 1) as $i => $destino) {
            $this->workflow->conexiones()->create(['nodo_origen_id' => $nodos[$i]->id, 'nodo_destino_id' => $destino->id]);
        }
    }

    private function iniciar(): int
    {
        return $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias")
            ->assertCreated()->json('data.id');
    }

    private function ejecutar(int $id, WorkflowNodo $nodo)
    {
        Tarea::where('instancia_id', $id)->where('nodo_id', $nodo->id)->where('estado', 'pendiente')->update(['estado' => 'completada']);

        return $this->actingAs($this->usuario)->postJson(
            "/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}/ejecutar",
            ['nodo_id' => $nodo->id]
        );
    }

    private function devolver(int $id, WorkflowNodo $origen, WorkflowNodo $destino, string $motivo = 'Falta el soporte firmado')
    {
        return $this->actingAs($this->usuario)->postJson(
            "/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}/devolver",
            ['nodo_origen_id' => $origen->id, 'nodo_destino_id' => $destino->id, 'motivo' => $motivo]
        );
    }

    private function estado(int $id, WorkflowNodo $nodo): string
    {
        return DB::table('workflow_nodos_instancia')->where('instancia_id', $id)->where('nodo_id', $nodo->id)->value('estado');
    }

    private function tareas(int $id, WorkflowNodo $nodo): array
    {
        return Tarea::where('instancia_id', $id)->where('nodo_id', $nodo->id)->orderBy('id')->pluck('estado')->all();
    }

    public function test_devuelve_a_un_paso_anterior_y_rehace_los_intermedios(): void
    {
        $inicio = $this->nodo('inicio', 'Inicio');
        $radicar = $this->nodo('tarea', 'Radicar');
        $revisar = $this->nodo('tarea', 'Revisar');
        $aprobar = $this->nodo('tarea', 'Aprobar');
        $fin = $this->nodo('fin', 'Fin');
        $this->cadena($inicio, $radicar, $revisar, $aprobar, $fin);
        $radicar->tareas()->create(['titulo' => 'Adjuntar soporte', 'orden' => 1]);
        $aprobar->tareas()->create(['titulo' => 'Firmar', 'orden' => 1]);

        $id = $this->iniciar();
        $this->ejecutar($id, $radicar)->assertOk();
        $this->ejecutar($id, $revisar)->assertOk();
        $this->assertSame(['pendiente'], $this->tareas($id, $aprobar));

        $this->devolver($id, $aprobar, $radicar)->assertOk()->assertJsonPath('data.nodo_actual_id', $radicar->id);

        $this->assertSame('en_curso', $this->estado($id, $radicar));
        $this->assertSame('pendiente', $this->estado($id, $revisar));
        $this->assertSame('pendiente', $this->estado($id, $aprobar));
        $this->assertSame(['completada', 'pendiente'], $this->tareas($id, $radicar));
        $this->assertSame(['cancelada'], $this->tareas($id, $aprobar));

        $devolucion = WorkflowInstancia::find($id)->datos_contexto['devoluciones'][0];
        $this->assertSame('Falta el soporte firmado', $devolucion['motivo']);
        $this->assertSame([$aprobar->id, $radicar->id, $this->usuario->id], [$devolucion['nodo_origen_id'], $devolucion['nodo_destino_id'], $devolucion['user_id']]);
        $this->assertDatabaseHas('workflow_audits', ['instancia_id' => $id, 'action' => 'instancia.devuelta']);

        // El reproceso sigue el flujo normal hasta el final
        $this->ejecutar($id, $radicar)->assertOk();
        $this->ejecutar($id, $revisar)->assertOk();
        $this->ejecutar($id, $aprobar)->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada']);
    }

    public function test_rechaza_devoluciones_invalidas(): void
    {
        $inicio = $this->nodo('inicio', 'Inicio');
        $radicar = $this->nodo('tarea', 'Radicar');
        $revisar = $this->nodo('tarea', 'Revisar');
        $aprobar = $this->nodo('tarea', 'Aprobar');
        $fin = $this->nodo('fin', 'Fin');
        $this->cadena($inicio, $radicar, $revisar, $aprobar, $fin);

        $id = $this->iniciar();
        $this->ejecutar($id, $radicar)->assertOk();

        $this->devolver($id, $revisar, $radicar, 'corto')->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->devolver($id, $revisar, $aprobar)->assertStatus(422);
        $this->devolver($id, $revisar, $inicio)->assertStatus(422);
        $this->devolver($id, $radicar, $revisar)->assertStatus(422);

        $sinPermiso = User::factory()->create(['estado' => 1]);
        $this->actingAs($sinPermiso)->postJson(
            "/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}/devolver",
            ['nodo_origen_id' => $revisar->id, 'nodo_destino_id' => $radicar->id, 'motivo' => 'Falta el soporte firmado']
        )->assertForbidden();

        $this->actingAs($this->usuario)->patchJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}/detener")->assertOk();
        $this->devolver($id, $revisar, $radicar)->assertStatus(422);
        $this->assertSame('en_curso', $this->estado($id, $revisar));
    }

    public function test_antes_de_un_paralelo_solo_se_devuelve_sin_otras_ramas_en_curso(): void
    {
        $inicio = $this->nodo('inicio', 'Inicio');
        $radicar = $this->nodo('tarea', 'Radicar');
        $paralelo = $this->nodo('paralelo', 'Revisiones');
        $juridica = $this->nodo('tarea', 'Revisión jurídica');
        $financiera = $this->nodo('tarea', 'Revisión financiera');
        $union = $this->nodo('union', 'Unión');
        $fin = $this->nodo('fin', 'Fin');
        $this->cadena($inicio, $radicar, $paralelo, $juridica, $union, $fin);
        $this->cadena($paralelo, $financiera, $union);

        $id = $this->iniciar();
        $this->ejecutar($id, $radicar)->assertOk();

        $this->devolver($id, $juridica, $radicar)->assertStatus(422)
            ->assertJsonFragment(['message' => 'No se puede devolver a ese paso mientras "Revisión financiera" sigue en curso en otra rama']);

        $this->ejecutar($id, $financiera)->assertOk();
        $this->assertSame('en_espera', $this->estado($id, $union));

        $this->devolver($id, $juridica, $radicar)->assertOk();
        $this->assertSame('en_curso', $this->estado($id, $radicar));
        foreach ([$paralelo, $juridica, $financiera, $union] as $nodo) {
            $this->assertSame('pendiente', $this->estado($id, $nodo), $nodo->titulo);
        }

        $this->ejecutar($id, $radicar)->assertOk();
        $this->assertSame('en_curso', $this->estado($id, $juridica));
        $this->assertSame('en_curso', $this->estado($id, $financiera));
    }
}
