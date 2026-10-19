<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B07 (diseño de expedientes): el tipo documental de cada folio sale del catálogo de la TRD de
 * la subserie del expediente, no de texto libre.
 */
class TiposDocumentalesExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    private int $acta;

    private int $informe;

    private int $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ofi_archivo_expedientes_documentos', 'tipo_documental_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_19_120000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Ver Todos', 'Ver', 'Incorporar', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B07', 'cod_organico' => 'B07D']);
        $nodo = fn (string $tipo, string $cod, ?int $parent = null, ?string $nom = null) => DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => $tipo, 'cod' => $cod, 'nom' => $nom ?? "{$tipo} {$cod}", 'parent' => $parent,
            'dependencia_id' => $depId, 'user_register' => $this->user->id,
        ]);
        $serie = $nodo('Serie', 'S07');
        $subserie = $nodo('SubSerie', 'S07.1', $serie);
        $this->informe = $nodo('TipoDocumento', 'T2', $subserie, 'Informe de supervisión');
        $this->acta = $nodo('TipoDocumento', 'T1', $subserie, 'Acta de inicio');
        $this->ajeno = $nodo('TipoDocumento', 'T3', $nodo('SubSerie', 'S07.2', $serie), 'Factura');
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B07D-S07-'.uniqid(), 'nombre_expediente' => 'Contrato 7', 'anio' => 2026,
            'dependencia_id' => $depId, 'serie_trd_id' => $serie, 'subserie_trd_id' => $subserie, 'estado' => 'abierto',
            'fecha_apertura' => '2026-10-19', 'usuario_apertura_id' => $this->user->id,
        ]);

        Storage::fake('archivo_expedientes');
    }

    private function subir(array $datos)
    {
        return $this->post("/api/archivo/expedientes/{$this->expediente->id}/archivos", $datos + [
            'archivos' => [UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")],
            'tipo' => 'tipo_documental',
        ], ['Accept' => 'application/json']);
    }

    public function test_el_catalogo_son_los_tipos_de_la_subserie(): void
    {
        $this->getJson("/api/archivo/expedientes/{$this->expediente->id}/tipos-documentales")
            ->assertOk()
            ->assertJsonPath('data.*.nom', ['Acta de inicio', 'Informe de supervisión']);
    }

    public function test_al_subir_el_folio_queda_tipificado_con_el_catalogo(): void
    {
        $doc = $this->subir(['tipo_documental_id' => $this->acta])->assertCreated()->json('data.0');
        $this->assertSame($this->acta, $doc['tipo_documental_id']);
        $this->assertSame('Acta de inicio', $doc['tipo_documental']);

        // Un cliente anterior que envía el nombre también queda tipificado
        $this->subir(['tipo_documental' => 'informe de supervisión'])
            ->assertCreated()->assertJsonPath('data.0.tipo_documental_id', $this->informe);

        // Ni un tipo de otra subserie ni texto libre
        $this->subir(['tipo_documental_id' => $this->ajeno])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'no pertenece a la subserie'));
        $this->subir(['tipo_documental' => 'Cualquier cosa'])->assertUnprocessable();

        $this->assertSame(2, OfiArchivoExpedienteDocumento::where('expediente_id', $this->expediente->id)->count());
    }

    public function test_un_folio_sin_tipo_se_tipifica_despues_y_queda_en_el_historial(): void
    {
        $doc = OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $this->expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Documento de flujo',
            'asunto' => 'Evidencia', 'fecha_documento' => '2026-10-19', 'usuario_id' => $this->user->id, 'activo' => true, 'orden' => 0,
        ]);
        $this->assertNull($doc->fresh()->tipo_documental_id);

        $url = "/api/archivo/expedientes/{$this->expediente->id}/documentos/{$doc->id}/tipo-documental";
        $this->putJson($url, ['tipo_documental_id' => $this->ajeno])->assertUnprocessable();
        $this->putJson($url, ['tipo_documental_id' => $this->informe])
            ->assertOk()
            ->assertJsonPath('data.tipo_documental_id', $this->informe)
            ->assertJsonPath('data.tipo_documental', 'Informe de supervisión');

        $evento = OfiArchivoExpedienteEvento::where('expediente_id', $this->expediente->id)->orderByDesc('secuencia')->first();
        $this->assertSame('tipificacion', $evento->accion);
        $this->assertSame(['documento_id' => $doc->id, 'numero_folio' => 1, 'antes' => 'Documento de flujo', 'despues' => 'Informe de supervisión'], $evento->detalle);

        $this->expediente->update(['estado' => 'cerrado']);
        $this->putJson($url, ['tipo_documental_id' => $this->acta])->assertUnprocessable();
    }
}
