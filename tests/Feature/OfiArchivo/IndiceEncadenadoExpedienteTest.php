<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndice;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\User;
use App\Services\OfiArchivo\IndiceExpedienteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B10 (diseño de expedientes): el índice electrónico se escribe al incorporar cada folio,
 * encadenado, y cada cierre lo congela en una versión firmada que se puede verificar.
 */
class IndiceEncadenadoExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ofi_archivo_expediente_indice') || ! Schema::hasTable('ofi_archivo_expediente_eventos')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_19_100000 y 2026_10_19_130000.');
        }

        $this->user = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Archivista']);
        foreach (['Ver Todos', 'Ver', 'Editar', 'Cerrar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B10', 'cod_organico' => 'B10D']);
        $serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'S10', 'nom' => 'Contratos', 'dependencia_id' => $depId, 'user_register' => $this->user->id,
        ]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B10D-S10-'.uniqid(), 'nombre_expediente' => 'Contrato 10', 'anio' => 2026,
            'dependencia_id' => $depId, 'serie_trd_id' => $serie, 'estado' => 'abierto', 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-10-19', 'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function folio(string $asunto): OfiArchivoExpedienteDocumento
    {
        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $this->expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Acta',
            'asunto' => $asunto, 'fecha_documento' => '2026-10-19', 'hash_sha256' => hash('sha256', $asunto), 'usuario_id' => $this->user->id, 'activo' => true, 'orden' => 0,
            'tipo_documental_id' => 1,
        ]);
    }

    private function cadena(): array
    {
        return app(IndiceExpedienteService::class)->verificarCadena($this->expediente);
    }

    public function test_cada_folio_queda_encadenado_y_retirarlo_lo_anula(): void
    {
        $uno = $this->folio('Acta de inicio');
        $dos = $this->folio('Informe');

        $filas = OfiArchivoExpedienteIndice::where('expediente_id', $this->expediente->id)->orderBy('orden')->get();
        $this->assertSame([1, 2], $filas->pluck('orden')->all());
        $this->assertNull($filas[0]->hash_anterior);
        $this->assertSame($filas[0]->hash_cadena, $filas[1]->hash_anterior);

        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/documentos/{$uno->id}/soft-delete")->assertOk();

        $anulada = OfiArchivoExpedienteIndice::where('documento_id', $uno->id)->first();
        $this->assertNotNull($anulada->anulado_en);
        $this->assertSame($this->user->id, $anulada->anulado_por);
        $this->assertTrue($this->cadena()['integra'], 'Anular un folio no rompe la cadena');
        $this->assertSame(2, $this->cadena()['total']);

        // Lo encadenado no se edita ni se borra
        $this->expectException(\LogicException::class);
        OfiArchivoExpedienteIndice::where('documento_id', $dos->id)->first()->update(['origen' => 'Otro']);
    }

    public function test_la_verificacion_detecta_filas_alteradas_y_huellas_cambiadas(): void
    {
        $this->folio('Acta de inicio');
        $dos = $this->folio('Informe');
        $this->folio('Cierre');

        // Alteración directa en la BD, saltándose el modelo
        DB::table('ofi_archivo_expediente_indice')->where('documento_id', $dos->id)->update(['fecha_incorporacion' => '2020-01-01 00:00:00']);
        DB::table('ofi_archivo_expedientes_documentos')->where('id', $dos->id)->update(['hash_sha256' => str_repeat('a', 64)]);

        $cadena = $this->cadena();
        $this->assertFalse($cadena['integra']);
        $this->assertEqualsCanonicalizing(
            ['Los datos de la fila fueron alterados', 'La huella del documento no coincide con la del índice'],
            collect($cadena['errores'])->where('numero_folio', 2)->pluck('motivo')->all()
        );
    }

    public function test_el_cierre_congela_el_indice_en_una_version_firmada(): void
    {
        $this->folio('Acta de inicio');
        $this->folio('Informe');

        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", ['motivo_cierre' => 'Terminó el trámite'])->assertOk();

        $version = OfiArchivoExpedienteIndiceVersion::where('expediente_id', $this->expediente->id)->sole();
        $this->assertSame(1, $version->version);
        $this->assertSame(2, $version->total_folios);
        $this->assertSame('Ana Archivista', $version->firmante);
        $this->assertSame($this->expediente->fresh()->hash_indice, $version->hash_indice);
        $this->assertStringContainsString($version->codigo_verificacion, $version->xml);
        $this->assertStringStartsWith('%PDF', $version->pdf);
        $this->assertNull($version->sello_tiempo, 'Sin ARCHIVO_INDICE_TSA no se llama a la TSA');

        $this->assertSame('indice_firmado', OfiArchivoExpedienteEvento::where('expediente_id', $this->expediente->id)
            ->orderByDesc('secuencia')->value('accion'));

        $this->getJson("/api/archivo/expedientes/{$this->expediente->id}/indice/versiones")
            ->assertOk()
            ->assertJsonPath('data.cadena.integra', true)
            ->assertJsonPath('data.versiones.0.codigo_verificacion', $version->codigo_verificacion)
            ->assertJsonPath('data.versiones.0.verificacion.valida', true)
            ->assertJsonMissingPath('data.versiones.0.pdf');

        $pdf = $this->get("/api/archivo/expedientes/{$this->expediente->id}/indice/versiones/1/pdf")->assertOk();
        $this->assertSame($version->hash_pdf, hash('sha256', $pdf->getContent()));

        // Alterar el PDF guardado invalida la versión
        DB::table('ofi_archivo_expediente_indice_versiones')->where('id', $version->id)->update(['pdf' => '%PDF-alterado']);
        $this->getJson("/api/archivo/expedientes/{$this->expediente->id}/indice/versiones")
            ->assertJsonPath('data.versiones.0.verificacion.valida', false)
            ->assertJsonPath('data.versiones.0.verificacion.motivo', 'El PDF no coincide con su huella');
    }
}
