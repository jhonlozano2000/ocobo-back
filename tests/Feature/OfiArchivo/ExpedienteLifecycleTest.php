<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests de lifecycle completo del módulo Expedientes Electrónicos.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-26
 */
class ExpedienteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CalidadOrganigrama $dependencia;
    protected ClasificacionDocumentalTRD $trd;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Gestion de Archivo -> Expedientes -> Ver',
            'Gestion de Archivo -> Expedientes -> Crear',
            'Gestion de Archivo -> Expedientes -> Editar',
            'Gestion de Archivo -> Expedientes -> Cerrar',
            'Gestion de Archivo -> Expedientes -> Incorporar',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Archivo Test']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);

        $this->dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dependencia Test',
            'cod_organico' => 'DEP01',
        ]);

        $this->trd = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-TEST-01',
            'nom' => 'Serie Test',
            'user_register' => $this->user->id,
        ]);
    }

    /** @test */
    public function puede_crear_expediente()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/archivo/expedientes', [
                'nombre_expediente' => 'Expediente de Prueba Unitaria',
                'dependencia_id' => $this->dependencia->id,
                'serie_trd_id' => $this->trd->id,
                'observaciones_generales' => 'Observación de prueba',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'numero_expediente', 'nombre_expediente', 'estado', 'anio'],
            ]);

        $this->assertDatabaseHas('ofi_archivo_expedientes', [
            'nombre_expediente' => 'Expediente de Prueba Unitaria',
            'estado' => 'abierto',
            'anio' => (int) now()->year,
        ]);
    }

    /** @test */
    public function crear_expediente_genera_numero_corректo()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/archivo/expedientes', [
                'nombre_expediente' => 'Expediente Num Test',
                'dependencia_id' => $this->dependencia->id,
                'serie_trd_id' => $this->trd->id,
            ]);

        $response->assertStatus(201);

        $numero = $response->json('data.numero_expediente');
        $this->assertMatchesRegularExpression('/^\d{4}-[\w-]+-[\w-]+-\d{4}$/', $numero);
    }

    /** @test */
    public function crear_expediente_sin_campos_requeridos_falla()
    {
        $this->actingAs($this->user)
            ->postJson('/api/archivo/expedientes', [])
            ->assertStatus(422);
    }

    /** @test */
    public function puede_ver_expediente()
    {
        $expediente = $this->crearExpediente();

        $this->actingAs($this->user)
            ->getJson("/api/archivo/expedientes/{$expediente->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $expediente->id);
    }

    /** @test */
    public function puede_listar_expedientes()
    {
        $this->crearExpediente();
        $this->crearExpediente();

        $this->actingAs($this->user)
            ->getJson('/api/archivo/expedientes')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }

    /** @test */
    public function puede_actualizar_expediente()
    {
        $expediente = $this->crearExpediente();

        $this->actingAs($this->user)
            ->putJson("/api/archivo/expedientes/{$expediente->id}", [
                'nombre_expediente' => 'Nombre Actualizado',
                'observaciones_generales' => 'Nuevas observaciones',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ofi_archivo_expedientes', [
            'id' => $expediente->id,
            'nombre_expediente' => 'Nombre Actualizado',
            'observaciones_generales' => 'Nuevas observaciones',
        ]);
    }

    /** @test */
    public function puede_cerrar_expediente()
    {
        $expediente = $this->crearExpediente();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/cerrar", [
                'motivo_cierre' => 'Cierre de prueba unitaria',
            ])
            ->assertStatus(200);

        $expediente->refresh();
        $this->assertEquals('cerrado', $expediente->estado);
        $this->assertNotNull($expediente->fecha_cierre);
        $this->assertNotNull($expediente->hash_indice);
        $this->assertEquals($this->user->id, $expediente->usuario_cierre_id);
    }

    /** @test */
    public function no_puede_cerrar_expediente_ya_cerrado()
    {
        $expediente = $this->crearExpediente();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/cerrar", [
                'motivo_cierre' => 'Primer cierre',
            ]);

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/cerrar", [
                'motivo_cierre' => 'Segundo cierre',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function puede_incorporar_documento()
    {
        $expediente = $this->crearExpediente();
        $radicadoId = $this->crearRadicadoReci();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/incorporar", [
                'documentable_id' => $radicadoId,
                'documentable_type' => 'radicado_recibido',
                'detalle' => 'Documento incorporado en prueba',
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'numero_folio']]);

        $this->assertDatabaseHas('ofi_archivo_expedientes_documentos', [
            'expediente_id' => $expediente->id,
            'activo' => true,
        ]);
    }

    /** @test */
    public function foliacion_es_secuencial()
    {
        $expediente = $this->crearExpediente();
        $id1 = $this->crearRadicadoReci();
        $id2 = $this->crearRadicadoReci();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/incorporar", [
                'documentable_id' => $id1,
                'documentable_type' => 'radicado_recibido',
            ]);

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/incorporar", [
                'documentable_id' => $id2,
                'documentable_type' => 'radicado_recibido',
            ]);

        $docs = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)
            ->orderBy('numero_folio')
            ->get();

        $this->assertCount(2, $docs);
        $this->assertEquals(1, $docs[0]->numero_folio);
        $this->assertEquals(2, $docs[1]->numero_folio);
    }

    /** @test */
    public function no_puede_incorporar_documento_duplicado()
    {
        $expediente = $this->crearExpediente();
        $radicadoId = $this->crearRadicadoReci();

        $payload = [
            'documentable_id' => $radicadoId,
            'documentable_type' => 'radicado_recibido',
        ];

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/incorporar", $payload)
            ->assertStatus(200);

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/incorporar", $payload)
            ->assertStatus(422);
    }

    /** @test */
    public function puede_subir_archivo()
    {
        Storage::fake('archivo_expedientes');
        $expediente = $this->crearExpediente();
        $archivo = $this->crearPdfFalso();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/archivos", [
                'archivos' => [$archivo],
                'tipo' => 'tipo_documental',
                'tipo_documental' => 'Oficio',
                'descripcion' => 'Prueba de subida',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('ofi_archivo_expedientes_documentos', [
            'expediente_id' => $expediente->id,
            'tipo' => 'tipo_documental',
            'tipo_documental' => 'Oficio',
            'activo' => true,
        ]);
    }

    /** @test */
    public function no_puede_subir_archivo_a_expediente_cerrado()
    {
        Storage::fake('archivo_expedientes');
        $expediente = $this->crearExpediente();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/cerrar", [
                'motivo_cierre' => 'Cierre',
            ]);

        $archivo = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/archivos", [
                'archivos' => [$archivo],
                'tipo' => 'expediente_completo',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function puede_eliminar_documento()
    {
        Storage::fake('archivo_expedientes');
        $expediente = $this->crearExpediente();
        $archivo = $this->crearPdfFalso();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/archivos", [
                'archivos' => [$archivo],
                'tipo' => 'expediente_completo',
            ])
            ->assertStatus(201);

        $doc = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)->first();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/documentos/{$doc->id}/soft-delete")
            ->assertStatus(200);

        $doc->refresh();
        $this->assertFalse($doc->activo);
    }

    /** @test */
    public function total_folios_se_incremeta_con_documento()
    {
        $expediente = $this->crearExpediente();
        $this->assertEquals(0, $expediente->total_folios_elec);

        $radicadoId = $this->crearRadicadoReci();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/incorporar", [
                'documentable_id' => $radicadoId,
                'documentable_type' => 'radicado_recibido',
            ]);

        $expediente->refresh();
        $this->assertEquals(1, $expediente->total_folios_elec);
    }

    /** @test */
    public function total_folios_se_decremeta_con_eliminar_documento()
    {
        Storage::fake('archivo_expedientes');
        $expediente = $this->crearExpediente();
        $archivo = $this->crearPdfFalso();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/archivos", [
                'archivos' => [$archivo],
                'tipo' => 'expediente_completo',
            ])
            ->assertStatus(201);

        $expediente->refresh();
        $this->assertEquals(1, $expediente->total_folios_elec);

        $doc = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)->first();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/documentos/{$doc->id}/soft-delete");

        $expediente->refresh();
        $this->assertEquals(0, $expediente->total_folios_elec);
    }

    /** @test */
    public function puede_generar_indice_pdf()
    {
        $expediente = $this->crearExpediente();

        $this->actingAs($this->user)
            ->getJson("/api/archivo/expedientes/{$expediente->id}/indice")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function sin_permiso_no_puede_crear_expediente()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/archivo/expedientes', [
                'nombre_expediente' => 'Test',
                'dependencia_id' => $this->dependencia->id,
                'serie_trd_id' => $this->trd->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function sin_permiso_no_puede_cerrar_expediente()
    {
        $user = User::factory()->create();
        $expediente = $this->crearExpediente();

        $this->actingAs($user)
            ->postJson("/api/archivo/expedientes/{$expediente->id}/cerrar", [
                'motivo_cierre' => 'Intento no autorizado',
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function busqueda_filtrar_por_dependencia()
    {
        $otraDep = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Otra Dependencia',
            'cod_organico' => 'DEP02',
        ]);

        $this->crearExpediente(['dependencia_id' => $this->dependencia->id]);
        $this->crearExpediente(['dependencia_id' => $otraDep->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/archivo/expedientes?dependencia_id={$this->dependencia->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    /** @test */
    public function busqueda_texto_filtrar_por_nombre()
    {
        $this->crearExpediente(['nombre_expediente' => 'Contrato de servicios']);
        $this->crearExpediente(['nombre_expediente' => 'Oficio de transmisión']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/archivo/expedientes?search=Contrato');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    /**
     * Crea un radicado recibido vía DB para evitar dependencias NOT NULL del modelo.
     */
    protected function crearRadicadoReci(): int
    {
        // Crear la cadena de FK: config_listas → config_listas_detalles → ventanilla_radica_reci
        $listaId = DB::table('config_listas')->insertGetId([
            'nombre' => 'Medios de Recepción',
            'cod' => 'MED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $medioId = DB::table('config_listas_detalles')->insertGetId([
            'lista_id' => $listaId,
            'codigo' => 'MD-01',
            'nombre' => 'Email',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'RAD-'.uniqid(),
            'asunto' => 'Documento de prueba',
            'medio_recep_id' => $medioId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Crea un archivo PDF real con magic bytes válidos para pasar la validación.
     */
    protected function crearPdfFalso(): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tmpFile, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 3 3]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%EOF");

        return new UploadedFile($tmpFile, 'documento.pdf', 'application/pdf', null, true);
    }

    protected function crearExpediente(array $overrides = []): OfiArchivoExpediente
    {
        $defaults = [
            'numero_expediente' => 'EXP-'.uniqid(),
            'nombre_expediente' => 'Expediente Test',
            'anio' => (int) now()->year,
            'dependencia_id' => $this->dependencia->id,
            'serie_trd_id' => $this->trd->id,
            'estado' => 'abierto',
            'fecha_apertura' => now()->toDateString(),
            'usuario_apertura_id' => $this->user->id,
        ];

        return OfiArchivoExpediente::create(array_merge($defaults, $overrides));
    }
}
