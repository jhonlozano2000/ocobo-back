<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkFlowTarea;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 20.12: los KPI contaban las plantillas WorkFlowTarea como «tareas de nodo» (siempre pendientes) y
 * mezclaban en «tareas de módulo» las tareas que nacen de los nodos. Desde M19-17 todas son Tarea:
 * con instancia = de nodo, sin instancia = manuales.
 */
class WorkflowReportesTest extends TestCase
{
    use DatabaseTransactions;

    private const PERMISOS = ['Workflows -> Workflows -> Listar', 'Workflows -> Workflows -> Mostrar'];

    private User $creador;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo(self::PERMISOS);
        $this->workflow = Workflow::create(['nombre' => 'Reportes 20.12', 'estado' => 'activo', 'creador_user_id' => $this->creador->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function instancia(string $estado = 'en_curso', ?string $inicio = null, ?string $fin = null): WorkflowInstancia
    {
        return WorkflowInstancia::create([
            'workflow_id' => $this->workflow->id, 'usuario_ejecuta_id' => $this->creador->id, 'estado' => $estado,
            'fecha_inicio' => $inicio ?? now(), 'fecha_fin' => $fin,
        ]);
    }

    private function tarea(string $estado, ?WorkflowInstancia $instancia = null, array $responsables = [], array $extra = []): Tarea
    {
        $tarea = Tarea::create([
            'workflow_id' => $this->workflow->id, 'instancia_id' => $instancia?->id, 'nombre' => "Tarea {$estado}", 'estado' => $estado,
        ] + $extra);
        $tarea->responsables()->attach($responsables);

        return $tarea;
    }

    private function reporte(string $ruta = '', ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->creador)->getJson("/api/workflows/workflows/{$this->workflow->id}/reportes{$ruta}");
    }

    public function test_el_resumen_separa_tareas_manuales_de_las_de_nodo_y_no_cuenta_plantillas(): void
    {
        $instancia = $this->instancia('en_curso');
        $this->instancia('completada', now()->subHours(10), now());

        $this->tarea('pendiente');
        $this->tarea('completada', extra: ['completada_at' => now()]);
        $this->tarea('completada', $instancia);
        $this->tarea('vencida', $instancia);
        $this->tarea('cancelada', $instancia);
        $this->tarea('en_curso', $instancia);

        // La plantilla del nodo no es una tarea real
        $nodo = WorkflowNodo::create(['workflow_id' => $this->workflow->id, 'tipo' => 'tarea', 'titulo' => 'Revisar']);
        WorkFlowTarea::create(['nodo_id' => $nodo->id, 'titulo' => 'Plantilla', 'estado' => 'pendiente']);

        $data = $this->reporte()->assertOk()->json('data');

        $this->assertEquals(
            ['total' => 2, 'pendiente' => 1, 'en_curso' => 0, 'completada' => 1, 'vencida' => 0, 'cancelada' => 0, 'tasa_completitud' => 50],
            $data['tareas_modulo']
        );
        $this->assertEquals(
            ['total' => 4, 'pendiente' => 0, 'en_curso' => 1, 'completada' => 1, 'vencida' => 1, 'cancelada' => 1, 'tasa_completitud' => 25],
            $data['tareas_nodo']
        );
        $this->assertSame(['total' => 2, 'en_curso' => 1, 'completada' => 1, 'detenida' => 0, 'cancelada' => 0], $data['instancias']);
        $this->assertEquals(10, $data['tiempos_promedio']['instancias_horas']);
        $this->assertEquals(0, $data['tiempos_promedio']['tareas_modulo_horas']);
        $this->assertNull($data['tiempos_promedio']['tareas_nodo_horas']);
    }

    public function test_un_workflow_sin_datos_responde_ceros(): void
    {
        $data = $this->reporte()->assertOk()->json('data');

        $this->assertSame(0, $data['tareas_nodo']['total']);
        $this->assertEquals(0, $data['tareas_nodo']['tasa_completitud']);
        $this->assertSame(0, $data['instancias']['total']);
        $this->assertNull($data['tiempos_promedio']['instancias_horas']);
        $this->assertSame([], $this->reporte('/vencidas-por-usuario')->assertOk()->json('data'));
    }

    public function test_vencidas_por_usuario_cuenta_manuales_y_de_nodo_de_mas_a_menos(): void
    {
        $ana = User::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Ruiz']);
        $luis = User::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Gómez']);
        $instancia = $this->instancia();

        $this->tarea('vencida', null, [$ana->id]);
        $this->tarea('vencida', $instancia, [$luis->id, $ana->id]);
        $this->tarea('vencida', $instancia, [$luis->id]);
        $this->tarea('vencida', $instancia, [$luis->id]);
        $this->tarea('pendiente', $instancia, [$ana->id]);

        $this->reporte('/vencidas-por-usuario')->assertOk()->assertExactJson([
            'status' => true, 'success' => true, 'message' => 'Datos obtenidos correctamente',
            'data' => [
                ['id' => $luis->id, 'name' => 'Luis Gómez', 'total' => 3],
                ['id' => $ana->id, 'name' => 'Ana Ruiz', 'total' => 2],
            ],
        ]);
    }

    public function test_la_tendencia_trae_todos_los_meses_aunque_no_haya_completadas(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->instancia('completada', '2026-08-01 08:00:00', '2026-08-03 08:00:00');
        $this->instancia('completada', '2026-08-01 08:00:00', '2026-08-20 08:00:00');
        $this->instancia('completada', '2026-10-01 08:00:00', '2026-10-02 08:00:00');
        // Fuera de la ventana de 3 meses y sin terminar
        $this->instancia('completada', '2026-07-01 08:00:00', '2026-07-31 23:00:00');
        $this->instancia('en_curso');

        $this->reporte('/tendencia-mensual?meses=3')->assertOk()->assertJsonPath('data', [
            ['mes' => '2026-08', 'total' => 2],
            ['mes' => '2026-09', 'total' => 0],
            ['mes' => '2026-10', 'total' => 1],
        ]);

        $this->assertCount(6, $this->reporte('/tendencia-mensual')->json('data'));
        $this->assertCount(24, $this->reporte('/tendencia-mensual?meses=500')->json('data'));
        $this->assertCount(1, $this->reporte('/tendencia-mensual?meses=-4')->json('data'));
    }

    public function test_solo_ve_los_kpi_quien_puede_ver_el_workflow(): void
    {
        $otro = User::factory()->create(['estado' => 1]);
        $otro->givePermissionTo(self::PERMISOS);

        $this->reporte('', $otro)->assertForbidden();
        $this->reporte('', User::factory()->create(['estado' => 1]))->assertForbidden();
    }
}
