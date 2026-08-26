<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkFlowArchivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de seguridad y ciclo de archivos del módulo Workflows.
 *
 * Cubre hallazgos de auditoría:
 * - C1/C2: subir() con validación segura (antes rompía por firma incorrecta)
 * - C4: IDOR — usuario B no puede descargar/eliminar archivos del workflow A
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-25
 */
class WorkflowsArchivoSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected User $userB;

    protected Workflow $workflowA;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Workflows -> Workflows -> Listar',
            'Workflows -> Archivos -> Ver',
            'Workflows -> Archivos -> Subir',
            'Workflows -> Archivos -> Descargar',
            'Workflows -> Archivos -> Eliminar',
        ];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Configurar disco fake para workflows_archivos
        Storage::fake('workflows_archivos');

        $rol = Role::firstOrCreate(['name' => 'WfArchivos Test']);
        $rol->givePermissionTo($permisos);

        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->userA->assignRole($rol);
        $this->userB->assignRole($rol);

        $this->workflowA = Workflow::create([
            'nombre' => 'Workflow A '.Str::random(4),
            'descripcion' => 'Test',
            'creador_user_id' => $this->userA->id,
            'estado' => 'activo',
        ]);
    }

    /** @test */
    public function sube_archivo_valido_con_hash(): void
    {
        // PDF mínimo válido (magic bytes %PDF- para pasar la validación real)
        $pdfValido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
            .'trailer<</Root 1 0 R>>%%EOF';

        $res = $this->actingAs($this->userA)
            ->postJson("/api/workflows/workflows/{$this->workflowA->id}/archivos", [
                'archivo' => UploadedFile::fake()->createWithContent('doc.pdf', $pdfValido),
                'archivable_type' => 'workflow',
                'archivable_id' => $this->workflowA->id,
            ]);

        $res->assertStatus(201);

        $archivo = WorkFlowArchivo::where('workflow_id', $this->workflowA->id)->first();
        $this->assertNotNull($archivo, 'El archivo debe persistirse');
        $this->assertNotNull($archivo->hash_sha256);
    }

    /** @test */
    public function rechaza_mime_spoofing(): void
    {
        // Un .txt disfrazado con extensión pdf pero contenido texto plano
        $res = $this->actingAs($this->userA)
            ->postJson("/api/workflows/workflows/{$this->workflowA->id}/archivos", [
                'archivo' => UploadedFile::fake()->createWithContent('malicioso.pdf', '<?php echo "x";'),
                'archivable_type' => 'workflow',
                'archivable_id' => $this->workflowA->id,
            ]);

        $res->assertStatus(422);
    }

    /** @test */
    public function descarga_requiere_pertenencia_al_workflow_idor(): void
    {
        $archivo = WorkFlowArchivo::create([
            'workflow_id' => $this->workflowA->id,
            'archivable_type' => Workflow::class,
            'archivable_id' => $this->workflowA->id,
            'nombre_original' => 'secreto.pdf',
            'nombre_almacenado' => Str::random(50).'.pdf',
            'ruta_almacenada' => Str::random(50).'.pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 100,
            'hash_sha256' => hash('sha256', 'contenido'),
            'disk' => 'workflows_archivos',
            'categoria' => 'adjunto',
            'uploaded_by' => $this->userA->id,
        ]);

        // Usuario B intenta descargar vía OTRO workflow (ID 99999): debe ser 404
        $res = $this->actingAs($this->userB)
            ->getJson('/api/workflows/workflows/99999/archivos/'.$archivo->id.'/download');

        $res->assertStatus(404);
    }

    /** @test */
    public function eliminacion_requiere_pertenencia_al_workflow_idor(): void
    {
        $archivo = WorkFlowArchivo::create([
            'workflow_id' => $this->workflowA->id,
            'archivable_type' => Workflow::class,
            'archivable_id' => $this->workflowA->id,
            'nombre_original' => 'secreto2.pdf',
            'nombre_almacenado' => Str::random(50).'.pdf',
            'ruta_almacenada' => Str::random(50).'.pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 100,
            'hash_sha256' => hash('sha256', 'contenido2'),
            'disk' => 'workflows_archivos',
            'categoria' => 'adjunto',
            'uploaded_by' => $this->userA->id,
        ]);

        // Usuario B intenta eliminar vía otro workflow: 404, el archivo sobrevive
        $res = $this->actingAs($this->userB)
            ->deleteJson('/api/workflows/workflows/99999/archivos/'.$archivo->id);

        $res->assertStatus(404);
        $this->assertDatabaseHas('work_flow_archivos', ['id' => $archivo->id]);
    }
}
