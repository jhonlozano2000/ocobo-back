<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
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
 * Con varios firmantes el documento queda pendiente hasta que firma el último; quien ya firmó
 * no lo vuelve a ver en pendientes ni puede firmarlo otra vez (individual y masiva).
 */
class FirmaVariosFirmantesTest extends TestCase
{
    use DatabaseTransactions;

    private const MASIVA = '/api/transversal/firma-electronica';

    private const INDIVIDUAL = '/api/archivo/firma-electronica';

    private User $ana;

    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        Storage::fake('radicados_internos');
        Storage::fake('radicados_enviados');
        // Sin TSA en pruebas: la firma no depende de la estampa de tiempo
        Http::fake(['*' => Http::response('', 503)]);
        if (! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_internos_responsa LIKE ventanilla_radica_interno_responsa');
        }
        [$this->ana, $this->beto] = User::factory()->count(2)->create(['estado' => 1])->all();
    }

    private function pdf(string $disco): array
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Varios firmantes');
        $contenido = $pdf->Output('S');
        $ruta = 'firmantes/'.uniqid().'.pdf';
        Storage::disk($disco)->put($ruta, $contenido);

        return [$ruta, hash('sha256', $contenido)];
    }

    private function interno(): int
    {
        [$ruta, $hash] = $this->pdf('radicados_internos');

        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'VF-'.uniqid(), 'clasifica_documen_id' => 1, 'usuario_crea' => $this->ana->id,
            'archivo_digital' => $ruta, 'hash_sha256' => $hash, 'estado_firma' => 'pendiente',
        ]);
        foreach ([$this->ana, $this->beto] as $u) {
            DB::table('ventanilla_radica_internos_firmantes')->insert(['radica_interno_id' => $id, 'users_id' => $u->id, 'firmado' => 0]);
        }
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function enviado(): int
    {
        [$ruta, $hash] = $this->pdf('radicados_enviados');

        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'VF-'.uniqid(), 'clasifica_documen_id' => 1, 'tercero_id' => 999999,
            'medio_enviado_id' => 999999, 'tipo_respuesta_id' => 999999, 'usuario_crea' => $this->ana->id,
            'archivo_digital' => $ruta, 'hash_sha256' => $hash, 'estado_firma' => 'pendiente',
        ]);
        foreach ([$this->ana, $this->beto] as $u) {
            $cargo = DB::table('users_cargos')->insertGetId([
                'user_id' => $u->id, 'cargo_id' => 999999, 'fecha_inicio' => now()->toDateString(), 'estado' => 1,
            ]);
            DB::table('ventanilla_radica_enviados_firmas')->insert(['radica_enviado_id' => $id, 'users_cargos_id' => $cargo]);
        }
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function otpEnviado(User $u): string
    {
        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use ($u, &$otp) {
            if ($mail->hasTo($u->email)) {
                $otp = (string) $mail->otp;
            }

            return true;
        });

        return $otp;
    }

    private function firmarLote(User $u, int $id)
    {
        $documentos = [['documentable_type' => 'radicado_interno', 'documentable_id' => $id]];
        $this->actingAs($u)->postJson(self::MASIVA.'/solicitar-otp-masivo', ['documentos' => $documentos])->assertOk();

        return $this->postJson(self::MASIVA.'/firmar-masivo', ['otp' => $this->otpEnviado($u), 'documentos' => $documentos])->assertOk();
    }

    private function firmarIndividual(User $u, int $id)
    {
        $payload = ['documentable_type' => 'radicado_enviado', 'documentable_id' => $id];
        $this->actingAs($u)->postJson(self::INDIVIDUAL.'/solicitar-otp', $payload)->assertOk();

        return $this->postJson(self::INDIVIDUAL.'/firmar', $payload + ['otp' => $this->otpEnviado($u)]);
    }

    private function pendientes(User $u)
    {
        return collect($this->actingAs($u)->getJson(self::MASIVA.'/pendientes')->assertOk()->json('data'))->pluck('documentable_id');
    }

    public function test_un_interno_por_lote_espera_al_ultimo_firmante(): void
    {
        $id = $this->interno();

        $this->firmarLote($this->ana, $id)->assertJsonPath('data.resultados.0.firma_completa', false);
        $radicado = VentanillaRadicaInterno::find($id);
        $this->assertSame('pendiente', $radicado->estado_firma);
        $this->assertNull($radicado->fecha_firma);
        $this->assertNotContains($id, $this->pendientes($this->ana));
        $this->assertContains($id, $this->pendientes($this->beto));

        $this->firmarLote($this->beto, $id)->assertJsonPath('data.resultados.0.firma_completa', true);
        $radicado->refresh();
        $this->assertSame('firmado', $radicado->estado_firma);
        $this->assertNotNull($radicado->fecha_firma);
        $this->assertNotContains($id, $this->pendientes($this->beto));
    }

    public function test_un_enviado_individual_espera_al_ultimo_firmante_y_no_se_firma_dos_veces(): void
    {
        $id = $this->enviado();
        $this->assertContains($id, $this->pendientes($this->ana));

        $this->firmarIndividual($this->ana, $id)->assertOk()
            ->assertJsonPath('data.firma_completa', false)
            ->assertJsonPath('message', 'Firma registrada; faltan otros firmantes');
        $this->assertSame('pendiente', VentanillaRadicaEnviados::find($id)->estado_firma);
        $this->assertNotContains($id, $this->pendientes($this->ana));
        $this->assertContains($id, $this->pendientes($this->beto));

        // Quien ya firmó no vuelve a estampar el PDF
        Mail::fake();
        $this->firmarIndividual($this->ana, $id)->assertJsonPath('message', 'Ya firmó este documento.');
        $this->assertSame(1, DB::table('firmas_eventos')->where('documentable_type', VentanillaRadicaEnviados::class)
            ->where('documentable_id', $id)->count());

        Mail::fake();
        $this->firmarIndividual($this->beto, $id)->assertOk()->assertJsonPath('data.firma_completa', true);
        $enviado = VentanillaRadicaEnviados::find($id);
        $this->assertSame('firmado', $enviado->estado_firma);
        $this->assertNotNull($enviado->fecha_firma);
    }
}
