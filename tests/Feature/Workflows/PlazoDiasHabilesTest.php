<?php

namespace Tests\Feature\Workflows;

use App\Models\Configuracion\ConfigCalendarioFestivo;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-30: los plazos del flujo, del nodo y de la plantilla de tarea pueden medirse en días hábiles
 * (sin fines de semana ni festivos, vencen al final del día) además de en horas.
 */
class PlazoDiasHabilesTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflows', 'tiempo_finalizacion_unidad') || ! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_17_120000 y 2026_10_18_120000.');
        }

        // Viernes 16 de octubre de 2026; el lunes 19 es festivo para la prueba
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00'));
        Cache::flush();
        ConfigVarias::setConsiderarDiasHabiles(true);
        ConfigCalendarioFestivo::where('fecha', '2026-10-19')->delete();
        ConfigCalendarioFestivo::create(['fecha' => '2026-10-19', 'nombre' => 'Festivo de prueba', 'tipo' => 'nacional', 'anio' => 2026]);

        foreach (['Workflows -> Workflows -> Crear', 'Workflows -> Workflows -> Mostrar', 'Workflows -> Instancias -> Ejecutar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo(['Workflows -> Workflows -> Crear', 'Workflows -> Workflows -> Mostrar', 'Workflows -> Instancias -> Ejecutar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_los_plazos_en_dias_habiles_saltan_fin_de_semana_y_festivos(): void
    {
        $workflowId = $this->actingAs($this->usuario)->postJson('/api/workflows/workflows', [
            'nombre' => 'Trámite con términos',
            'tiempo_finalizacion_horas' => 2,
            'tiempo_finalizacion_unidad' => 'dias_habiles',
        ])->assertCreated()->json('data.id');

        $workflow = Workflow::findOrFail($workflowId);
        $this->assertSame('dias_habiles', $workflow->tiempo_finalizacion_unidad);
        $workflow->update(['estado' => 'activo']);

        $nodo = fn (string $tipo, array $extra = []) => $workflow->nodos()->create(
            ['tipo' => $tipo, 'titulo' => ucfirst($tipo), 'posicion_x' => 0, 'posicion_y' => 0] + $extra
        );
        $inicio = $nodo('inicio');
        $revision = $nodo('tarea', ['tiempo_limite_horas' => 1, 'tiempo_limite_unidad' => 'dias_habiles']);
        $fin = $nodo('fin');
        $workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $revision->id]);
        $workflow->conexiones()->create(['nodo_origen_id' => $revision->id, 'nodo_destino_id' => $fin->id]);
        $revision->tareas()->create(['titulo' => 'Revisar', 'orden' => 1]);
        $revision->tareas()->create(['titulo' => 'Avisar', 'orden' => 2, 'tiempo_limite_horas' => 3, 'tiempo_limite_unidad' => 'horas']);

        $id = $this->actingAs($this->usuario)
            ->postJson("/api/workflows/workflows/{$workflowId}/instancias")
            ->assertCreated()->json('data.id');

        // Viernes + 2 días hábiles: el lunes es festivo, así que martes y miércoles
        $this->assertSame('2026-10-21 23:59:59', WorkflowInstancia::findOrFail($id)->fecha_limite_estimada->format('Y-m-d H:i:s'));

        [$revisar, $avisar] = Tarea::where('instancia_id', $id)->orderBy('id')->get();
        // Sin plazo propio hereda el del nodo (1 día hábil); el de la plantilla, en horas, manda
        $this->assertSame('2026-10-20 23:59:59', $revisar->fecha_limite->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-16 13:00:00', $avisar->fecha_limite->format('Y-m-d H:i:s'));
    }

    public function test_la_unidad_se_valida_y_se_conserva_al_duplicar(): void
    {
        $this->actingAs($this->usuario)->postJson('/api/workflows/workflows', [
            'nombre' => 'Unidad inválida',
            'tiempo_finalizacion_horas' => 2,
            'tiempo_finalizacion_unidad' => 'semanas',
        ])->assertStatus(422)->assertJsonValidationErrors('tiempo_finalizacion_unidad');

        $original = Workflow::create([
            'nombre' => 'Original', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id,
            'tiempo_finalizacion_horas' => 10, 'tiempo_finalizacion_unidad' => 'dias_habiles',
        ]);
        $original->nodos()->create([
            'tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0,
            'tiempo_limite_horas' => 5, 'tiempo_limite_unidad' => 'dias_habiles',
        ]);

        $copiaId = $this->actingAs($this->usuario)
            ->postJson("/api/workflows/workflows/{$original->id}/duplicar")
            ->assertSuccessful()->json('data.id');

        $copia = Workflow::with('nodos')->findOrFail($copiaId);
        $this->assertSame('dias_habiles', $copia->tiempo_finalizacion_unidad);
        $this->assertSame(5, (int) $copia->nodos->first()->tiempo_limite_horas);
        $this->assertSame('dias_habiles', $copia->nodos->first()->tiempo_limite_unidad);
    }
}
