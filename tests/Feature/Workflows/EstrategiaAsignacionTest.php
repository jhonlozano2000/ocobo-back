<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-26: estrategia_asignacion se guardaba pero nadie la usaba. Ahora reparte las tareas de nodo
 * que el diseño no fija a una persona: fila (rotación) o autoasignar (la toma quien la inicia).
 */
class EstrategiaAsignacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $disenador;

    /** @var list<User> */
    private array $equipo;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_17_120000 (instancia/nodo en workflow_tareas).');
        }

        $permisos = [
            'Workflows -> Workflows -> Crear', 'Workflows -> Workflows -> Editar',
            'Workflows -> Instancias -> Ejecutar', 'Workflows -> Tareas -> Editar',
        ];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->disenador = User::factory()->create(['estado' => 1]);
        $this->disenador->givePermissionTo($permisos);
        $this->equipo = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach ($this->equipo as $miembro) {
            $miembro->givePermissionTo('Workflows -> Tareas -> Editar');
        }

        $this->workflow = Workflow::create(['nombre' => 'M19-26', 'estado' => 'activo', 'creador_user_id' => $this->disenador->id]);
        $nodo = fn (string $tipo) => $this->workflow->nodos()->create(
            ['tipo' => $tipo, 'titulo' => ucfirst($tipo), 'posicion_x' => 0, 'posicion_y' => 0]
        );
        $inicio = $nodo('inicio');
        $revision = $nodo('tarea');
        $fin = $nodo('fin');
        $this->workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $revision->id]);
        $this->workflow->conexiones()->create(['nodo_origen_id' => $revision->id, 'nodo_destino_id' => $fin->id]);
        $revision->tareas()->create(['titulo' => 'Revisar', 'orden' => 1]);
    }

    private function configurar(string $estrategia, array $equipo): void
    {
        $this->actingAs($this->disenador)->putJson("/api/workflows/workflows/{$this->workflow->id}", [
            'estrategia_asignacion' => $estrategia,
            'equipo_ids' => array_map(fn (User $u) => $u->id, $equipo),
        ])->assertOk();
    }

    /** @return list<int> */
    private function responsablesDeNuevaInstancia(): array
    {
        $instanciaId = $this->actingAs($this->disenador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias")
            ->assertCreated()
            ->json('data.id');

        return Tarea::where('instancia_id', $instanciaId)->sole()->responsables()->pluck('users.id')->all();
    }

    public function test_fila_rota_entre_los_miembros_activos_del_equipo(): void
    {
        [$ana, $beto, $caro] = $this->equipo;
        $this->configurar('fila', $this->equipo);

        $this->assertSame([$ana->id], $this->responsablesDeNuevaInstancia());
        $this->assertSame([$beto->id], $this->responsablesDeNuevaInstancia());

        $caro->update(['estado' => 0]);
        $this->assertSame([$ana->id], $this->responsablesDeNuevaInstancia());
    }

    public function test_autoasignar_la_envia_al_equipo_y_se_la_queda_quien_la_inicia(): void
    {
        [$ana, $beto] = $this->equipo;
        $this->configurar('autoasignar', [$ana, $beto]);

        $this->assertEqualsCanonicalizing([$ana->id, $beto->id], $this->responsablesDeNuevaInstancia());
        $tarea = Tarea::latest('id')->first();
        $this->assertDatabaseHas('notifications', ['user_id' => $beto->id, 'notifiable_id' => $tarea->id, 'type' => 'tarea.asignada']);

        $this->actingAs($beto)->postJson("/api/workflows/tareas/{$tarea->id}/iniciar")->assertOk();
        $this->assertSame([$beto->id], $tarea->responsables()->pluck('users.id')->all());

        $this->actingAs($ana)->postJson("/api/workflows/tareas/{$tarea->id}/iniciar")->assertForbidden();
    }

    public function test_la_configuracion_se_valida(): void
    {
        $url = "/api/workflows/workflows/{$this->workflow->id}";

        $this->actingAs($this->disenador)->putJson($url, ['estrategia_asignacion' => 'fila'])
            ->assertUnprocessable()->assertJsonValidationErrors('equipo_ids');
        $this->actingAs($this->disenador)->putJson($url, ['estrategia_asignacion' => 'automatizacion'])
            ->assertUnprocessable()->assertJsonValidationErrors('estrategia_asignacion');
        $this->actingAs($this->disenador)->putJson($url, ['estrategia_asignacion' => 'manual'])->assertOk();

        // Sin estrategia de equipo sigue el comportamiento anterior: quien ejecuta
        $this->assertSame([$this->disenador->id], $this->responsablesDeNuevaInstancia());
    }
}
