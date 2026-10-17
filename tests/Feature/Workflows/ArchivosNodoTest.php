<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-46: el builder enviaba archivable_type con el FQCN; el request solo conocía 'nodo'/'instancia',
 * caía en Workflow::class y consultaba workflows.workflow_id (columna inexistente) → 500 en cada subida.
 */
class ArchivosNodoTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    private Workflow $workflow;

    private WorkflowNodo $nodo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('workflows_archivos');
        foreach (['Subir', 'Ver'] as $accion) {
            Permission::findOrCreate("Workflows -> Archivos -> {$accion}", 'web');
        }
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo(['Workflows -> Archivos -> Subir', 'Workflows -> Archivos -> Ver']);
        $this->workflow = Workflow::create([
            'nombre' => 'Archivos M19-46', 'estado' => 'borrador', 'creador_user_id' => $this->usuario->id,
        ]);
        $this->nodo = $this->workflow->nodos()->create([
            'tipo' => 'tarea', 'titulo' => 'Revisión', 'posicion_x' => 0, 'posicion_y' => 0,
        ]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('instructivo.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    public function test_sube_y_lista_un_archivo_del_nodo_con_el_alias(): void
    {
        $url = "/api/workflows/workflows/{$this->workflow->id}/archivos";

        $this->actingAs($this->usuario)
            ->post($url, ['archivo' => $this->pdf(), 'archivable_type' => 'nodo', 'archivable_id' => $this->nodo->id], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertDatabaseHas('work_flow_archivos', [
            'workflow_id' => $this->workflow->id, 'archivable_type' => WorkflowNodo::class, 'archivable_id' => $this->nodo->id,
        ]);

        $this->actingAs($this->usuario)
            ->getJson("{$url}?archivable_type=nodo&archivable_id={$this->nodo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_un_tipo_desconocido_da_422_y_no_500(): void
    {
        $this->actingAs($this->usuario)
            ->post("/api/workflows/workflows/{$this->workflow->id}/archivos", [
                'archivo' => $this->pdf(), 'archivable_type' => WorkflowNodo::class, 'archivable_id' => $this->nodo->id,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivable_type');
    }
}
