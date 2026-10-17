<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-05: nodos, tareas de nodo, checklists, archivos y reportes solo exigían el permiso plano;
 * cualquiera con el permiso modificaba o consultaba workflows ajenos, y {nodo}/{tarea} no se
 * comprobaban contra el workflow de la ruta.
 */
class AutorizacionAnidadaTest extends TestCase
{
    use DatabaseTransactions;

    private const PERMISOS = [
        'Workflows -> Workflows -> Listar', 'Workflows -> Workflows -> Mostrar', 'Workflows -> Workflows -> Editar',
        'Workflows -> Tareas -> Listar', 'Workflows -> Tareas -> Crear', 'Workflows -> Tareas -> Editar',
        'Workflows -> Archivos -> Subir', 'Workflows -> Archivos -> Ver', 'Workflows -> Archivos -> Eliminar',
    ];

    private User $creador;

    private User $ajeno;

    private Workflow $workflow;

    private WorkflowNodo $nodo;

    private int $tareaId;

    protected function setUp(): void
    {
        parent::setUp();

        // Asignar debe existir (UpdateWorkFlowTareaRequest lo consulta) aunque nadie lo tenga
        foreach ([...self::PERMISOS, 'Workflows -> Tareas -> Asignar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo(self::PERMISOS);
        $this->ajeno->givePermissionTo(self::PERMISOS);

        $this->workflow = Workflow::create(['nombre' => 'ABAC M19-05', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id]);
        $this->nodo = $this->workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisión', 'posicion_x' => 0, 'posicion_y' => 0]);
        $this->tareaId = DB::table('work_flow_tareas')->insertGetId([
            'nodo_id' => $this->nodo->id, 'titulo' => 'Revisar', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function base(): string
    {
        return "/api/workflows/workflows/{$this->workflow->id}";
    }

    public function test_un_editor_ajeno_no_modifica_el_diseno(): void
    {
        $this->actingAs($this->ajeno);

        $this->putJson("{$this->base()}/nodos/{$this->nodo->id}", ['titulo' => 'Hackeado'])->assertForbidden();
        $this->deleteJson("{$this->base()}/nodos/{$this->nodo->id}")->assertForbidden();
        $this->postJson("{$this->base()}/nodos/{$this->nodo->id}/tareas", ['titulo' => 'Intrusa'])->assertForbidden();
        $this->putJson("{$this->base()}/nodos/{$this->nodo->id}/tareas/{$this->tareaId}", ['titulo' => 'Otra'])->assertForbidden();
        $this->postJson("{$this->base()}/nodos/{$this->nodo->id}/tareas/{$this->tareaId}/checklists", ['item_descripcion' => 'x'])->assertForbidden();

        $this->assertDatabaseHas('workflow_nodos', ['id' => $this->nodo->id, 'titulo' => 'Revisión']);
        $this->assertDatabaseHas('work_flow_tareas', ['id' => $this->tareaId, 'titulo' => 'Revisar']);
    }

    public function test_un_usuario_ajeno_no_ve_los_reportes(): void
    {
        $this->actingAs($this->ajeno)->getJson("{$this->base()}/reportes")->assertForbidden();
        $this->actingAs($this->creador)->getJson("{$this->base()}/reportes")->assertOk();
    }

    public function test_un_nodo_de_otro_workflow_da_404(): void
    {
        $otro = Workflow::create(['nombre' => 'Otro', 'estado' => 'borrador', 'creador_user_id' => $this->creador->id]);

        $this->actingAs($this->creador)
            ->getJson("/api/workflows/workflows/{$otro->id}/nodos/{$this->nodo->id}/tareas")
            ->assertNotFound();
    }

    public function test_el_creador_sigue_disenando(): void
    {
        $this->actingAs($this->creador)
            ->putJson("{$this->base()}/nodos/{$this->nodo->id}/tareas/{$this->tareaId}", ['titulo' => 'Revisar v2'])
            ->assertOk();
    }

    public function test_adjuntos_del_nodo_solo_los_sube_el_creador(): void
    {
        Storage::fake('workflows_archivos');
        $datos = fn () => [
            'archivo' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"),
            'archivable_type' => 'nodo',
            'archivable_id' => $this->nodo->id,
        ];

        $this->actingAs($this->ajeno)->post("{$this->base()}/archivos", $datos(), ['Accept' => 'application/json'])->assertForbidden();
        $id = $this->actingAs($this->creador)->post("{$this->base()}/archivos", $datos(), ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->ajeno)->deleteJson("{$this->base()}/archivos/{$id}")->assertForbidden();
        $this->actingAs($this->creador)->deleteJson("{$this->base()}/archivos/{$id}")->assertOk();
    }
}
