<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Rules\PdfFirmable;
use App\Services\Firma\PdfCompatible;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 13.4/13.5: la firma masiva estampa cada PDF del lote, registra su evento legal y marca al
 * firmante; un documento sin PDF falla solo, sin tumbar el resto del lote.
 */
class FirmaMasivaPdfTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/api/transversal/firma-electronica';

    private User $firmante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        Storage::fake('radicados_internos');
        // Sin TSA en pruebas: la firma no depende de la estampa de tiempo
        Http::fake(['*' => Http::response('', 503)]);
        if (! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_internos_responsa LIKE ventanilla_radica_interno_responsa');
        }
        $this->firmante = User::factory()->create(['estado' => 1]);
    }

    private function interno(bool $conPdf = true, bool $conHash = true, bool $cifrado = false, bool $comprimido = false): int
    {
        $ruta = 'm13/'.uniqid().'.pdf';
        $hash = null;
        if ($conPdf) {
            $pdf = new \FPDF;
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(40, 10, 'Lote 13.4');
            $contenido = $pdf->Output('S');
            if ($cifrado) {
                // Un PDF protegido declara /Encrypt en el trailer
                $contenido = str_replace('trailer
<<
', 'trailer
<<
/Encrypt 1 0 R
', $contenido);
            }
            if ($comprimido) {
                $contenido = self::conReferenciasComprimidas($contenido);
            }
            Storage::disk('radicados_internos')->put($ruta, $contenido);
            // Como lo deja la subida del digital
            $hash = $conHash ? hash('sha256', $contenido) : null;
        }

        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'M13-'.uniqid(), 'clasifica_documen_id' => 1, 'usuario_crea' => $this->firmante->id,
            'archivo_digital' => $ruta, 'hash_sha256' => $hash, 'estado_firma' => 'pendiente',
        ]);
        DB::table('ventanilla_radica_internos_firmantes')->insert([
            'radica_interno_id' => $id, 'users_id' => $this->firmante->id, 'firmado' => 0,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    /**
     * Agrega una actualización cuya tabla de referencias es un flujo /XRef (PDF 1.5+): la versión libre
     * de FPDI la rechaza (COMPRESSED_XREF) igual que a los PDF que exportan Word o los escáneres.
     */
    private static function conReferenciasComprimidas(string $pdf, bool $cifrado = false): string
    {
        $offset = strlen($pdf);
        $encrypt = $cifrado ? ' /Encrypt 1 0 R' : '';

        return $pdf.implode("\n", [
            '99 0 obj', "<< /Type /XRef /Size 100 /W [1 1 1]{$encrypt} /Length 0 >>", 'stream', '', 'endstream', 'endobj',
            'startxref', $offset, '%%EOF', '',
        ]);
    }

    private function firmarLote(int ...$ids)
    {
        $documentos = array_map(fn ($id) => ['documentable_type' => 'radicado_interno', 'documentable_id' => $id], $ids);

        $this->actingAs($this->firmante)->postJson(self::BASE.'/solicitar-otp-masivo', ['documentos' => $documentos])->assertOk();
        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        return $this->postJson(self::BASE.'/firmar-masivo', ['otp' => $otp, 'documentos' => $documentos])->assertOk();
    }

    public function test_firma_todos_los_pdf_del_lote(): void
    {
        $a = $this->interno();
        $b = $this->interno();
        $hashAntes = hash('sha256', Storage::disk('radicados_internos')->get(VentanillaRadicaInterno::find($a)->archivo_digital));

        $this->firmarLote($a, $b)
            ->assertJsonPath('data.resumen', ['total' => 2, 'exitosos' => 2, 'fallidos' => 0]);

        foreach ([$a, $b] as $id) {
            $radicado = VentanillaRadicaInterno::find($id);
            $hashActual = hash('sha256', Storage::disk('radicados_internos')->get($radicado->archivo_digital));

            $this->assertSame('firmado', $radicado->estado_firma);
            $this->assertSame($hashActual, $radicado->hash_sha256);
            $this->assertTrue(FirmaEvento::where('documentable_type', VentanillaRadicaInterno::class)
                ->where('documentable_id', $id)->where('user_id', $this->firmante->id)
                ->where('hash_firmado', $hashActual)->exists());
            $this->assertSame(1, (int) DB::table('ventanilla_radica_internos_firmantes')
                ->where('radica_interno_id', $id)->value('firmado'));
        }
        $this->assertNotSame($hashAntes, VentanillaRadicaInterno::find($a)->hash_sha256);

        // Ya no aparecen como pendientes
        $pendientes = collect($this->getJson(self::BASE.'/pendientes')->assertOk()->json('data'))->pluck('documentable_id');
        $this->assertNotContains($a, $pendientes);
        $this->assertNotContains($b, $pendientes);
    }

    public function test_un_radicado_sin_hash_registrado_se_firma_con_el_hash_del_archivo(): void
    {
        $id = $this->interno(conHash: false);
        $hashArchivo = hash('sha256', Storage::disk('radicados_internos')->get(VentanillaRadicaInterno::find($id)->archivo_digital));

        $this->firmarLote($id)->assertJsonPath('data.resumen.exitosos', 1);

        $this->assertSame($hashArchivo, FirmaEvento::where('documentable_type', VentanillaRadicaInterno::class)
            ->where('documentable_id', $id)->value('hash_original'));
    }

    public function test_un_documento_sin_pdf_falla_solo(): void
    {
        $bueno = $this->interno();
        $roto = $this->interno(conPdf: false);

        $resultados = collect($this->firmarLote($bueno, $roto)
            ->assertJsonPath('data.resumen', ['total' => 2, 'exitosos' => 1, 'fallidos' => 1])
            ->json('data.resultados'))->keyBy('documentable_id');

        $this->assertTrue($resultados[$bueno]['success']);
        $this->assertFalse($resultados[$roto]['success']);
        $this->assertSame('pendiente', VentanillaRadicaInterno::find($roto)->estado_firma);
        $this->assertSame(0, (int) DB::table('ventanilla_radica_internos_firmantes')
            ->where('radica_interno_id', $roto)->value('firmado'));
    }

    public function test_un_pdf_protegido_falla_con_un_mensaje_claro(): void
    {
        $protegido = $this->interno(cifrado: true);

        $this->firmarLote($protegido)
            ->assertJsonPath('data.resumen.fallidos', 1)
            ->assertJsonPath('data.resultados.0.error', PdfFirmable::MENSAJE);

        $this->assertSame(0, (int) DB::table('ventanilla_radica_internos_firmantes')
            ->where('radica_interno_id', $protegido)->value('firmado'));
    }

    public function test_un_pdf_clasico_no_pasa_por_qpdf(): void
    {
        Process::fake();

        $this->firmarLote($this->interno())->assertJsonPath('data.resumen.exitosos', 1);

        Process::assertNothingRan();
    }

    public function test_un_pdf_con_referencias_comprimidas_se_convierte_con_qpdf_y_se_firma(): void
    {
        $id = $this->interno(comprimido: true);
        // qpdf deja la tabla clásica: el fake escribe la copia sin la actualización comprimida
        Process::fake(function ($proceso) {
            [, , $entrada, $salida] = $proceso->command;
            file_put_contents($salida, substr(file_get_contents($entrada), 0, strrpos(file_get_contents($entrada), '99 0 obj')));

            return Process::result();
        });

        $this->firmarLote($id)->assertJsonPath('data.resumen.exitosos', 1);

        Process::assertRan(fn ($proceso) => in_array('--object-streams=disable', $proceso->command, true));
        $this->assertSame('firmado', VentanillaRadicaInterno::find($id)->estado_firma);
    }

    public function test_sin_qpdf_un_pdf_comprimido_falla_con_un_mensaje_claro(): void
    {
        $id = $this->interno(comprimido: true);
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: "'qpdf' no se reconoce como un comando"));

        $this->firmarLote($id)
            ->assertJsonPath('data.resumen.fallidos', 1)
            ->assertJsonPath('data.resultados.0.error', PdfCompatible::MENSAJE_SIN_QPDF);

        $this->assertSame(0, (int) DB::table('ventanilla_radica_internos_firmantes')
            ->where('radica_interno_id', $id)->value('firmado'));
    }

    public function test_al_subir_se_aceptan_los_pdf_comprimidos_y_se_rechazan_los_protegidos(): void
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $clasico = $pdf->Output('S');

        foreach ([[false, false], [true, true]] as [$cifrado, $esperado]) {
            $ruta = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($ruta, self::conReferenciasComprimidas($clasico, $cifrado));
            $this->assertSame($esperado, PdfFirmable::estaCifrado($ruta));
            @unlink($ruta);
        }
    }
}
