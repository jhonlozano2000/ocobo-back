<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-27: la instancia solo podía estar en un nodo; no había ramas paralelas (división y unión).
 */
class RamasParalelasTest extends TestCase
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
        $this->workflow = Workflow::create(['nombre' => 'M19-27 paralelo', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
    }

    private function nodo(string $tipo, string $titulo): WorkflowNodo
    {
        return $this->workflow->nodos()->create(['tipo' => $tipo, 'titulo' => $titulo, 'posicion_x' => 0, 'posicion_y' => 0]);
    }

    private function conectar(WorkflowNodo $origen, WorkflowNodo $destino, ?string $etiqueta = null): void
    {
        $this->workflow->conexiones()->create(['nodo_origen_id' => $origen->id, 'nodo_destino_id' => $destino->id, 'etiqueta' => $etiqueta]);
    }

    private function iniciar(): int
    {
        return $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias")
            ->assertCreated()->json('data.id');
    }

    private function ejecutar(int $id, WorkflowNodo $nodo, array $resultado = [])
    {
        return $this->actingAs($this->usuario)->postJson(
            "/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}/ejecutar",
            ['nodo_id' => $nodo->id, 'resultado' => $resultado]
        );
    }

    private function estado(int $id, WorkflowNodo $nodo): string
    {
        return DB::table('workflow_nodos_instancia')->where('instancia_id', $id)->where('nodo_id', $nodo->id)->value('estado');
    }

    public function test_el_paralelo_abre_todas_las_ramas_y_la_union_espera_a_todas(): void
    {
        $inicio = $this->nodo('inicio', 'Inicio');
        $paralelo = $this->nodo('paralelo', 'Revisiones');
        $juridica = $this->nodo('tarea', 'Revisión jurídica');
        $financiera = $this->nodo('tarea', 'Revisión financiera');
        $union = $this->nodo('union', 'Unión');
        $firma = $this->nodo('tarea', 'Firma');
        $fin = $this->nodo('fin', 'Fin');
        $this->conectar($inicio, $paralelo);
        $this->conectar($paralelo, $juridica);
        $this->conectar($paralelo, $financiera);
        $this->conectar($juridica, $union);
        $this->conectar($financiera, $union);
        $this->conectar($union, $firma);
        $this->conectar($firma, $fin);

        $id = $this->iniciar();
        $this->assertSame('completado', $this->estado($id, $paralelo));
        $this->assertSame('en_curso', $this->estado($id, $juridica));
        $this->assertSame('en_curso', $this->estado($id, $financiera));

        $this->ejecutar($id, $juridica)->assertOk();
        $this->assertSame('en_espera', $this->estado($id, $union));
        $this->assertSame('pendiente', $this->estado($id, $firma));
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $financiera->id]);

        $this->ejecutar($id, $union)->assertStatus(422);
        $this->ejecutar($id, $firma)->assertStatus(422);

        $this->ejecutar($id, $financiera)->assertOk();
        $this->assertSame('completado', $this->estado($id, $union));
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $firma->id]);

        $this->ejecutar($id, $firma)->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada', 'nodo_actual_id' => $fin->id]);
    }

    public function test_la_union_no_espera_una_rama_que_termino_en_un_fin(): void
    {
        $inicio = $this->nodo('inicio', 'Inicio');
        $paralelo = $this->nodo('paralelo', 'Paralelo');
        $aval = $this->nodo('tarea', 'Aval');
        $condicion = $this->nodo('condicion', '¿Requiere comité?');
        $union = $this->nodo('union', 'Unión');
        $fin = $this->nodo('fin', 'Fin');
        $sinComite = $this->nodo('fin', 'Sin comité');
        $this->conectar($inicio, $paralelo);
        $this->conectar($paralelo, $aval);
        $this->conectar($paralelo, $condicion);
        $this->conectar($aval, $union);
        $this->conectar($condicion, $union, 'Sí');
        $this->conectar($condicion, $sinComite, 'No');
        $this->conectar($union, $fin);

        $id = $this->iniciar();
        $this->ejecutar($id, $aval)->assertOk();
        $this->assertSame('en_espera', $this->estado($id, $union));

        $this->ejecutar($id, $condicion, ['decision' => 'No'])->assertOk();
        $this->assertSame('completado', $this->estado($id, $sinComite));
        $this->assertSame('completado', $this->estado($id, $union));
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada', 'nodo_actual_id' => $fin->id]);
    }

    public function test_una_rama_que_acaba_en_fin_no_cierra_la_instancia_con_otra_abierta(): void
    {
        $inicio = $this->nodo('inicio', 'Inicio');
        $paralelo = $this->nodo('paralelo', 'Paralelo');
        $aviso = $this->nodo('tarea', 'Avisar');
        $tramite = $this->nodo('tarea', 'Tramitar');
        $finAviso = $this->nodo('fin', 'Fin aviso');
        $fin = $this->nodo('fin', 'Fin');
        $this->conectar($inicio, $paralelo);
        $this->conectar($paralelo, $aviso);
        $this->conectar($paralelo, $tramite);
        $this->conectar($aviso, $finAviso);
        $this->conectar($tramite, $fin);

        $id = $this->iniciar();
        $this->ejecutar($id, $aviso)->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso', 'nodo_actual_id' => $tramite->id]);

        $this->ejecutar($id, $tramite)->assertOk();
        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'completada', 'nodo_actual_id' => $fin->id]);
    }

    public function test_el_canvas_acepta_y_valida_paralelos(): void
    {
        Permission::findOrCreate('Workflows -> Workflows -> Editar', 'web');
        $this->usuario->givePermissionTo('Workflows -> Workflows -> Editar');
        $nodo = fn ($id, $tipo) => ['client_id' => $id, 'tipo' => $tipo, 'titulo' => $tipo, 'posicion_x' => 0, 'posicion_y' => 0];
        $conexion = fn ($o, $d) => ['nodo_origen_id' => $o, 'nodo_destino_id' => $d];
        $nodos = [$nodo('i', 'inicio'), $nodo('p', 'paralelo'), $nodo('a', 'tarea'), $nodo('u', 'union'), $nodo('f', 'fin')];

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", [
            'nodos' => $nodos,
            'conexiones' => [$conexion('i', 'p'), $conexion('p', 'a'), $conexion('a', 'u'), $conexion('u', 'f')],
        ])->assertStatus(422)->assertJsonFragment(['Cada nodo paralelo necesita al menos dos salidas']);

        $this->actingAs($this->usuario)->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", [
            'nodos' => [...$nodos, $nodo('b', 'tarea')],
            'conexiones' => [$conexion('i', 'p'), $conexion('p', 'a'), $conexion('p', 'b'), $conexion('a', 'u'), $conexion('b', 'u'), $conexion('u', 'f')],
        ])->assertOk();
    }
}
