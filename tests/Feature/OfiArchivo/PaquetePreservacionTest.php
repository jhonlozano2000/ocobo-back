<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use App\Services\OfiArchivo\HojaControlService;
use App\Services\OfiArchivo\IndiceExpedienteService;
use App\Services\OfiArchivo\PaquetePreservacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

/**
 * B23 (diseño de expedientes §3.9): la transferencia secundaria no validaba los formatos de
 * preservación ni la integridad del índice y del historial, y no entregaba un paquete con los
 * archivos y sus huellas.
 */
class PaquetePreservacionTest extends TestCase
{
    use DatabaseTransactions;

    private const PDF_A = "%PDF-1.7\n1 0 obj << /Type /Metadata /Subtype /XML >> stream\n<x:xmpmeta><rdf:Description pdfaid:part=\"2\" pdfaid:conformance=\"B\"/></x:xmpmeta>\nendstream endobj\n%%EOF";

    private const PDF = "%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF";

    private User $envia;

    private User $recibe;

    private int $dependencia;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archivo_expedientes');

        $this->envia = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Envía']);
        $this->recibe = User::factory()->create(['estado' => 1, 'nombres' => 'Luis', 'apellidos' => 'Recibe']);
        foreach (['Transferencias -> Ver', 'Transferencias -> Crear', 'Transferencias -> Recibir', 'Expedientes -> Ver Todos'] as $accion) {
            $permiso = Permission::findOrCreate('Gestion de Archivo -> '.$accion, 'web');
            $this->envia->givePermissionTo($permiso);
            $this->recibe->givePermissionTo($permiso);
        }
        $this->dependencia = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Oficina B23', 'cod_organico' => 'B23',
        ]);
    }

    /**
     * Expediente del archivo central con la retención total cumplida y un folio por archivo
     * (nombre => contenido). Su índice se firma solo si se pide.
     */
    private function expediente(array $archivos = [], bool $firmado = false, array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'P'.random_int(1000, 9999), 'nom' => 'Historias', 'user_register' => $this->envia->id,
            'a_g' => '2', 'a_c' => '3', 'ct' => 1, 'e' => 0, 'm_d' => 0, 's' => 0,
        ]);
        $id = DB::table('ofi_archivo_expedientes')->insertGetId($extra + [
            'numero_expediente' => 'B23-'.uniqid(), 'nombre_expediente' => 'Historia de prueba', 'anio' => 2018,
            'dependencia_id' => $this->dependencia, 'dependencia_cod' => 'B23', 'dependencia_nom' => 'Oficina B23',
            'serie_trd_id' => $serie, 'estado' => 'abierto', 'fase' => 'central', 'fecha_apertura' => now()->subYears(8),
            'usuario_apertura_id' => $this->envia->id, 'fecha_cierre' => now()->subYears(6),
        ]);
        Schema::enableForeignKeyConstraints();

        $orden = 0;
        foreach ($archivos as $nombre => $contenido) {
            $path = "exp{$id}/{$nombre}";
            Storage::disk('archivo_expedientes')->put($path, $contenido);
            OfiArchivoExpedienteDocumento::create([
                'expediente_id' => $id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Historia', 'orden' => ++$orden,
                'fecha_documento' => now()->subYears(7)->toDateString(), 'asunto' => $nombre, 'archivo_path' => $path,
                'nombre_original' => $nombre, 'hash_sha256' => hash('sha256', $contenido), 'tamano_bytes' => strlen($contenido),
                'usuario_id' => $this->envia->id,
            ]);
        }
        DB::table('ofi_archivo_expedientes')->where('id', $id)->update(['estado' => 'cerrado']);
        if ($firmado) {
            app(IndiceExpedienteService::class)->versionar(OfiArchivoExpediente::findOrFail($id), $this->envia);
        }

        return $id;
    }

    private function enviar(array $expedientes)
    {
        return $this->actingAs($this->envia)->postJson('/api/archivo/transferencia-lotes', [
            'tipo' => 'secundaria', 'dependencia_id' => $this->dependencia, 'origen' => 'Archivo central',
            'destino' => 'Archivo histórico', 'expediente_ids' => $expedientes,
        ]);
    }

    private function recibir(int $lote): void
    {
        $items = DB::table('ofi_archivo_transferencias')->where('lote_id', $lote)->pluck('id')
            ->map(fn ($id) => ['id' => $id, 'conforme' => true])->all();
        $this->actingAs($this->recibe)->postJson("/api/archivo/transferencia-lotes/{$lote}/recibir", ['items' => $items])
            ->assertOk()->assertJsonPath('data.estado', 'recibido');
    }

    public function test_solo_pasan_los_formatos_de_preservacion_y_el_pdf_debe_ser_pdf_a(): void
    {
        $apto = $this->expediente([
            'informe.pdf' => self::PDF_A,
            'plano.png' => "\x89PNG\r\n\x1a\n".random_bytes(32),
            'datos.csv' => "a;b\n1;2\n",
        ]);
        $pdfComun = $this->expediente(['informe.pdf' => self::PDF_A, 'anexo.pdf' => self::PDF]);
        $word = $this->expediente(['carta.docx' => "PK\x03\x04".random_bytes(40)]);

        $casos = [
            $pdfComun => 'el folio 2 (PDF que no es PDF/A)',
            $word => 'el folio 1 (formato no admitido: docx)',
        ];
        foreach ($casos as $exp => $motivo) {
            $errores = $this->enviar([$apto, $exp])->assertStatus(422)->json('errors.expediente_ids');
            $this->assertCount(1, $errores);
            $this->assertStringContainsString('No está en formatos de preservación: '.$motivo, $errores[0]);
        }

        $candidatos = collect($this->actingAs($this->envia)->getJson("/api/archivo/transferencia-lotes/candidatos?tipo=secundaria&dependencia_id={$this->dependencia}")
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertNull($candidatos[$apto]['motivo']);
        $this->assertStringContainsString('PDF/A', $candidatos[$pdfComun]['motivo']);

        $this->enviar([$apto])->assertCreated();
    }

    public function test_al_enviar_firma_el_indice_y_la_hoja_que_faltan(): void
    {
        $sinFirma = $this->expediente(['informe.pdf' => self::PDF_A]);
        $fisico = $this->expediente([], true, ['soporte' => 'fisico', 'ubicacion_fisica' => 'Depósito 2', 'caja' => '7', 'carpeta' => '1']);
        $this->assertNull(app(HojaControlService::class)->ultimaVersion(OfiArchivoExpediente::findOrFail($fisico)));

        $this->enviar([$sinFirma, $fisico])->assertCreated();

        $this->assertDatabaseHas('ofi_archivo_expediente_indice_versiones', [
            'expediente_id' => $sinFirma, 'version' => 1, 'motivo' => PaquetePreservacionService::MOTIVO_FIRMA, 'firmado_por' => $this->envia->id,
        ]);
        // El físico ya tenía su índice firmado: solo se firma su hoja de control
        $this->assertDatabaseMissing('ofi_archivo_expediente_indice_versiones', ['expediente_id' => $fisico, 'version' => 2]);
        $this->assertDatabaseHas('ofi_archivo_expediente_hoja_control_versiones', [
            'expediente_id' => $fisico, 'motivo' => PaquetePreservacionService::MOTIVO_FIRMA, 'firmado_por' => $this->envia->id,
        ]);
    }

    /** Las tablas son inmutables en la BD: se simula una firma forjada y un evento colado en el historial. */
    public function test_rechaza_el_indice_con_firma_invalida_o_el_historial_roto(): void
    {
        $apto = $this->expediente(['informe.pdf' => self::PDF_A]);
        $historialRoto = $this->expediente(['informe.pdf' => self::PDF_A]);
        $ultimo = DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $historialRoto)->orderByDesc('secuencia')->first();
        DB::table('ofi_archivo_expediente_eventos')->insert([
            'expediente_id' => $historialRoto, 'secuencia' => $ultimo->secuencia + 2, 'accion' => 'modificacion',
            'detalle' => '{}', 'ocurrido_en' => now(), 'hash_anterior' => $ultimo->hash, 'hash' => str_repeat('0', 64),
        ]);
        $errores = $this->enviar([$apto, $historialRoto])->assertStatus(422)->json('errors.expediente_ids');
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('Su historial no está íntegro (evento '.($ultimo->secuencia + 2).': Falta el evento', $errores[0]);

        // Firmado con otra llave, el índice ya no supera la verificación
        $forjado = $this->expediente(['informe.pdf' => self::PDF_A], true);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $errores = $this->enviar([$apto, $forjado])->assertStatus(422)->json('errors.expediente_ids');
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('Su índice firmado no supera la verificación (La firma no corresponde a la versión)', $errores[0]);
        $this->assertDatabaseCount('ofi_archivo_transferencia_lotes', 0);
    }

    public function test_la_secundaria_recibida_entrega_su_paquete_con_manifiesto_firmado(): void
    {
        $png = "\x89PNG\r\n\x1a\n".random_bytes(64);
        $exp = $this->expediente(['informe.pdf' => self::PDF_A, 'plano de obra.png' => $png]);
        $lote = $this->enviar([$exp])->assertCreated()->json('data.id');

        // Antes de recibirse no hay paquete
        $this->actingAs($this->envia)->get("/api/archivo/transferencia-lotes/{$lote}/paquete")->assertStatus(422);
        $this->recibir($lote);

        $respuesta = $this->actingAs($this->envia)->get("/api/archivo/transferencia-lotes/{$lote}/paquete")->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'zip');
        copy($respuesta->baseResponse->getFile()->getPathname(), $ruta);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));
        $numero = DB::table('ofi_archivo_expedientes')->where('id', $exp)->value('numero_expediente');
        $codigo = DB::table('ofi_archivo_transferencia_lotes')->where('id', $lote)->value('codigo');

        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $rutas = array_column($manifiesto['archivos'], 'ruta');
        foreach (["FUID_{$codigo}.xlsx", "Acta_Transferencia_{$codigo}.pdf", "{$numero}/indice.xml", "{$numero}/indice.pdf", "{$numero}/documentos/0001_informe.pdf", "{$numero}/documentos/0002_plano_de_obra.png"] as $esperada) {
            $this->assertContains($esperada, $rutas);
        }
        foreach ($manifiesto['archivos'] as $archivo) {
            $contenido = $zip->getFromName($archivo['ruta']);
            $this->assertSame($archivo['sha256'], hash('sha256', $contenido), $archivo['ruta']);
            $this->assertSame($archivo['bytes'], strlen($contenido));
        }
        $this->assertSame($png, $zip->getFromName("{$numero}/documentos/0002_plano_de_obra.png"));
        $this->assertSame(PaquetePreservacionService::firmar($manifiesto), $manifiesto['firma']);
        $this->assertSame('Ana Envía', $manifiesto['generado_por']);
        $zip->close();
        unlink($ruta);

        // Un archivo alterado después de recibirse impide armar el paquete
        Storage::disk('archivo_expedientes')->put("exp{$exp}/plano de obra.png", 'otro contenido');
        $this->assertStringContainsString('folio 2', $this->actingAs($this->envia)->getJson("/api/archivo/transferencia-lotes/{$lote}/paquete")
            ->assertStatus(422)->json('errors.lote.0'));
    }
}
