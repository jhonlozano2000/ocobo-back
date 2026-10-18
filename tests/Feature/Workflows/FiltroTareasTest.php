<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-38: las listas de tareas paginan en el servidor; la búsqueda y el filtro de estado
 * se hacían en el front sobre la primera página y no encontraban el resto.
 */
class FiltroTareasTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private Workflow $workflow;

    private Tarea $firmar;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo(['Workflows -> Tareas -> Listar', 'Workflows -> Workflows -> Mostrar']);
        $this->workflow = Workflow::create([
            'nombre' => 'Filtros M19-38', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id,
        ]);
        $this->firmar = Tarea::create(['workflow_id' => $this->workflow->id, 'nombre' => 'Firmar contrato', 'estado' => 'en_curso']);
        $revisar = Tarea::create(['workflow_id' => $this->workflow->id, 'nombre' => 'Revisar', 'descripcion' => 'Antes de firmar', 'estado' => 'pendiente']);
        $this->firmar->responsables()->attach($this->creador->id);
        $revisar->responsables()->attach($this->creador->id);
    }

    public function test_las_tareas_del_workflow_se_filtran_por_busqueda_y_estado(): void
    {
        $url = "/api/workflows/{$this->workflow->id}/tareas";

        $this->actingAs($this->creador)->getJson("{$url}?search=firmar")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->creador)->getJson("{$url}?search=firmar&estado=en_curso")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->firmar->id)
            ->assertJsonPath('pagination.total', 1);
        $this->actingAs($this->creador)->getJson("{$url}?estado=inventado")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_mis_tareas_se_filtran_por_busqueda_y_estado(): void
    {
        $this->actingAs($this->creador)->getJson('/api/workflows/mis-tareas?search=contrato')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->firmar->id);
        $this->actingAs($this->creador)->getJson('/api/workflows/mis-tareas?estado=pendiente')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
