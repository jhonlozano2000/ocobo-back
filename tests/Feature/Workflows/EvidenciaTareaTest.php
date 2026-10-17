<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-35: no había forma de adjuntar evidencia a una Tarea.
 */
class EvidenciaTareaTest extends TestCase
{
    use DatabaseTransactions;

    private User $propietario;

    private User $responsable;

    private User $ajeno;

    private Tarea $tarea;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('workflows_archivos');
        $permisos = ['Archivos -> Subir', 'Archivos -> Ver', 'Archivos -> Descargar', 'Archivos -> Eliminar', 'Tareas -> Mostrar', 'Tareas -> Editar'];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate("Workflows -> {$permiso}", 'web');
        }
        [$this->propietario, $this->responsable, $this->ajeno] = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach ([$this->propietario, $this->responsable, $this->ajeno] as $usuario) {
            $usuario->givePermissionTo(array_map(fn ($p) => "Workflows -> {$p}", $permisos));
        }

        $workflow = Workflow::create(['nombre' => 'M19-35', 'estado' => 'activo', 'creador_user_id' => $this->propietario->id]);
        $this->tarea = Tarea::create(['workflow_id' => $workflow->id, 'nombre' => 'Revisar', 'estado' => 'en_curso']);
        $this->tarea->propietarios()->attach($this->propietario->id);
        $this->tarea->responsables()->attach($this->responsable->id);
    }

    private function subir(User $usuario)
    {
        $pdf = UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return $this->actingAs($usuario)->post(
            "/api/workflows/tareas/{$this->tarea->id}/archivos",
            ['archivo' => $pdf],
            ['Accept' => 'application/json']
        );
    }

    public function test_el_responsable_adjunta_evidencia_y_el_propietario_la_ve_y_descarga(): void
    {
        $id = $this->subir($this->responsable)->assertCreated()->json('data.id');

        $this->assertDatabaseHas('work_flow_archivos', [
            'id' => $id, 'archivable_type' => Tarea::class, 'archivable_id' => $this->tarea->id,
            'categoria' => 'evidencia', 'uploaded_by' => $this->responsable->id,
        ]);
        $this->actingAs($this->propietario)->getJson("/api/workflows/tareas/{$this->tarea->id}/archivos")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre_original', 'acta.pdf');
        $this->actingAs($this->propietario)->get("/api/workflows/tareas/{$this->tarea->id}/archivos/{$id}/download")
            ->assertOk()->assertDownload('acta.pdf');
    }

    public function test_un_usuario_ajeno_no_sube_ni_ve_la_evidencia(): void
    {
        $id = $this->subir($this->responsable)->json('data.id');

        $this->subir($this->ajeno)->assertForbidden();
        $this->actingAs($this->ajeno)->getJson("/api/workflows/tareas/{$this->tarea->id}/archivos")->assertForbidden();
        $this->actingAs($this->ajeno)->get("/api/workflows/tareas/{$this->tarea->id}/archivos/{$id}/download")->assertForbidden();
        $this->actingAs($this->ajeno)->deleteJson("/api/workflows/tareas/{$this->tarea->id}/archivos/{$id}")->assertForbidden();
    }

    public function test_la_evidencia_de_una_tarea_cerrada_no_se_toca(): void
    {
        $id = $this->subir($this->responsable)->json('data.id');
        $this->tarea->update(['estado' => 'completada']);

        $this->subir($this->responsable)->assertStatus(422);
        $this->actingAs($this->responsable)->deleteJson("/api/workflows/tareas/{$this->tarea->id}/archivos/{$id}")->assertStatus(422);
        $this->assertDatabaseHas('work_flow_archivos', ['id' => $id]);
    }

    public function test_quien_subio_la_evidencia_la_borra_con_la_tarea_abierta(): void
    {
        $id = $this->subir($this->responsable)->json('data.id');

        $this->actingAs($this->responsable)->deleteJson("/api/workflows/tareas/{$this->tarea->id}/archivos/{$id}")->assertOk();
        $this->assertDatabaseMissing('work_flow_archivos', ['id' => $id]);
    }

    public function test_un_archivo_de_otra_tarea_da_404(): void
    {
        $otra = Tarea::create(['workflow_id' => $this->tarea->workflow_id, 'nombre' => 'Otra', 'estado' => 'en_curso']);
        $otra->propietarios()->attach($this->propietario->id);
        $id = $this->subir($this->responsable)->json('data.id');

        $this->actingAs($this->propietario)->get("/api/workflows/tareas/{$otra->id}/archivos/{$id}/download")->assertNotFound();
    }
}
