<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    private function interno(bool $conPdf = true, bool $conHash = true): int
    {
        $ruta = 'm13/'.uniqid().'.pdf';
        $hash = null;
        if ($conPdf) {
            $pdf = new \FPDF;
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(40, 10, 'Lote 13.4');
            $contenido = $pdf->Output('S');
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
}
