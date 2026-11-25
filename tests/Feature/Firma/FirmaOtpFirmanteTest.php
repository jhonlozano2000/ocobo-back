<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\Notificacion;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\Firma\FirmaService;
use App\Services\Firma\FirmaValidacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M06-02/06/07: el OTP de firma solo lo obtiene el firmante designado, llega a su propio
 * correo, queda ligado a documento + usuario, se guarda como hash y admite intentos limitados.
 */
class FirmaOtpFirmanteTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/archivo/firma-electronica';

    protected User $firmante;

    protected User $ajeno;

    protected VentanillaRadicaReci $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Mail::fake();

        $this->firmante = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->actingAs($this->firmante);

        // Medio de recepción y cargo no son relevantes para estas pruebas
        Schema::disableForeignKeyConstraints();
        $this->radicado = VentanillaRadicaReci::create([
            'num_radicado' => 'M06-TEST-0001',
            'asunto' => 'Prueba M06',
            'fechor_radica' => now(),
            'estado_trabajo' => 'RECIBIDO',
            'medio_recep_id' => 999999,
            'usuario_crea' => $this->firmante->id,
        ]);
        $userCargoId = DB::table('users_cargos')->insertGetId([
            'user_id' => $this->firmante->id,
            'cargo_id' => 999999,
            'fecha_inicio' => now()->toDateString(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ventanilla_radica_reci_responsa')->insert([
            'radica_reci_id' => $this->radicado->id,
            'users_cargos_id' => $userCargoId,
            'custodio' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function payload(array $extra = []): array
    {
        return ['documentable_type' => 'radicado_recibido', 'documentable_id' => $this->radicado->id] + $extra;
    }

    private function solicitarOtpComoFirmante(): string
    {
        $this->actingAs($this->firmante)->postJson(self::URL.'/solicitar-otp', $this->payload())->assertOk();

        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otp) {
            $otp = (string) $mail->otp;

            return $mail->hasTo($this->firmante->email);
        });

        return $otp;
    }

    public function test_usuario_no_designado_no_obtiene_otp(): void
    {
        $this->actingAs($this->ajeno)->postJson(self::URL.'/solicitar-otp', $this->payload())->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_firmante_designado_recibe_otp_en_su_propio_correo(): void
    {
        $otp = $this->solicitarOtpComoFirmante();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $guardado = Cache::get("otp_firma_radicado_recibido_{$this->radicado->id}_{$this->firmante->id}");
        $this->assertIsArray($guardado);
        $this->assertStringNotContainsString($otp, json_encode($guardado));
    }

    public function test_otp_de_un_firmante_no_sirve_a_otro_usuario(): void
    {
        $otp = $this->solicitarOtpComoFirmante();

        $this->actingAs($this->ajeno)->postJson(self::URL.'/firmar', $this->payload(['otp' => $otp]))->assertForbidden();
    }

    public function test_otp_es_de_un_solo_uso_y_tiene_intentos_limitados(): void
    {
        $service = app(FirmaService::class);
        $otp = $this->solicitarOtpComoFirmante();
        $id = $this->radicado->id;
        $uid = $this->firmante->id;

        $incorrecto = $otp === '000000' ? '111111' : '000000';
        for ($i = 0; $i < FirmaService::OTP_MAX_INTENTOS; $i++) {
            $this->assertFalse($service->validarOtpPorDocumento($incorrecto, 'radicado_recibido', $id, $uid));
        }

        // Agotados los intentos, ni el OTP correcto sirve
        $this->assertFalse($service->validarOtpPorDocumento($otp, 'radicado_recibido', $id, $uid));

        Mail::fake();
        $otp = $this->solicitarOtpComoFirmante();
        $this->assertTrue($service->validarOtpPorDocumento($otp, 'radicado_recibido', $id, $uid));
        $this->assertFalse($service->validarOtpPorDocumento($otp, 'radicado_recibido', $id, $uid));
    }

    public function test_firmar_tiene_limite_de_peticiones(): void
    {
        // M06-03: limiter 'firma' (5/min por usuario)
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $this->payload(['otp' => '000000']))->assertForbidden();
        }

        $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $this->payload(['otp' => '000000']))->assertStatus(429);
    }

    public function test_flujo_legado_sin_estampado_ya_no_existe(): void
    {
        // M06-11: /api/transversal/firma-electronica registraba la firma sin estampar el PDF
        // (hash_firmado null, hash_original enviado por el cliente); el front no lo usaba
        $legado = '/api/transversal/firma-electronica';

        $this->actingAs($this->firmante)->postJson($legado.'/solicitar-otp', ['documento_type' => 'radicado_recibido', 'documento_id' => $this->radicado->id])->assertNotFound();
        $this->actingAs($this->firmante)->postJson($legado.'/firmar', ['otp' => '123456', 'documento_type' => 'radicado_recibido', 'documento_id' => $this->radicado->id])->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_firma_estampa_el_pdf_y_registra_el_hash_firmado_verificable(): void
    {
        // M06-11: la firma estampa el sello (FPDI) y hash_firmado es el SHA-256 del PDF guardado
        if (! Schema::hasColumn('firmas_eventos', 'timestamp_token')) {
            $this->markTestSkipped('BD de pruebas sin la migración add_timestamp_token_to_firmas_eventos_table.');
        }

        Storage::fake('radicados_recibidos');
        Http::fake(['*' => Http::response('', 503)]);

        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Documento de prueba M06-11');
        $original = $pdf->Output('S');
        Storage::disk('radicados_recibidos')->put('m06/prueba.pdf', $original);
        $this->radicado->update(['archivo_digital' => 'm06/prueba.pdf', 'hash_sha256' => hash('sha256', $original)]);

        $otp = $this->solicitarOtpComoFirmante();
        $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $this->payload(['otp' => $otp]))->assertOk();

        $firmado = Storage::disk('radicados_recibidos')->get('m06/prueba.pdf');
        $this->assertNotSame($original, $firmado);

        $evento = FirmaEvento::where('documentable_type', VentanillaRadicaReci::class)
            ->where('documentable_id', $this->radicado->id)->sole();
        $this->assertSame(hash('sha256', $original), $evento->hash_original);
        $this->assertSame(hash('sha256', $firmado), $evento->hash_firmado);
        // La TSA no respondió: la firma se completa sin sello y sin fecha inventada
        $this->assertNull($evento->timestamp_token);
        $this->assertNull($evento->timestamp_fecha);
        $this->assertSame(hash('sha256', $firmado), $this->radicado->fresh()->hash_sha256);

        $validacion = app(FirmaValidacionService::class)->validar('radicado_recibido', $this->radicado->id);
        $this->assertTrue($validacion['valido']);

        // Cualquier cambio posterior al PDF firmado se detecta
        Storage::disk('radicados_recibidos')->put('m06/prueba.pdf', $firmado.'%alterado');
        $this->assertFalse(app(FirmaValidacionService::class)->validar('radicado_recibido', $this->radicado->id)['valido']);
    }

    public function test_firmante_designado_puede_firmar_con_password_sin_otp(): void
    {
        Storage::fake('radicados_recibidos');
        Http::fake(['*' => Http::response('', 503)]);
        $this->firmante->forceFill(['password' => Hash::make('ClaveDePrueba123')])->save();

        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Firma con password');
        $original = $pdf->Output('S');
        Storage::disk('radicados_recibidos')->put('m06/password.pdf', $original);
        $this->radicado->update(['archivo_digital' => 'm06/password.pdf', 'hash_sha256' => hash('sha256', $original)]);

        $payload = $this->payload(['metodo' => 'password', 'password' => 'incorrecta']);
        $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $payload)->assertForbidden();
        $this->assertDatabaseMissing('firmas_eventos', [
            'documentable_type' => VentanillaRadicaReci::class,
            'documentable_id' => $this->radicado->id,
        ]);

        $this->actingAs($this->ajeno)->postJson(self::URL.'/firmar', $this->payload([
            'metodo' => 'password', 'password' => 'ClaveDePrueba123',
        ]))->assertForbidden();

        $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $this->payload([
            'metodo' => 'password', 'password' => 'ClaveDePrueba123',
        ]))->assertOk();

        $evento = FirmaEvento::where('documentable_type', VentanillaRadicaReci::class)
            ->where('documentable_id', $this->radicado->id)->sole();
        $this->assertSame('PASSWORD', $evento->otp_utilizado);
        $this->assertSame(hash('sha256', Storage::disk('radicados_recibidos')->get('m06/password.pdf')), $evento->hash_firmado);
        Mail::assertNothingSent();
    }

    public function test_radicador_solicita_firma_con_password_y_notifica_al_responsable_sin_firmar(): void
    {
        $radicador = User::factory()->create([
            'estado' => 1,
            'password' => Hash::make('ClaveRadicador123'),
        ]);
        $this->radicado->update([
            'usuario_crea' => $radicador->id,
            'archivo_digital' => 'm06/pendiente.pdf',
            'estado_firma' => 'pendiente',
        ]);

        $url = self::URL.'/solicitar-firma';
        $payload = ['documentable_id' => $this->radicado->id, 'metodo' => 'password', 'password' => 'ClaveRadicador123'];

        $this->actingAs($radicador)->postJson($url, [...$payload, 'password' => 'incorrecta'])->assertForbidden();
        $this->actingAs($this->ajeno)->postJson($url, $payload)->assertForbidden();
        $this->actingAs($radicador)->postJson($url, $payload)
            ->assertOk()->assertJsonPath('data.responsables_notificados', 1);
        $this->actingAs($radicador)->postJson($url, $payload)->assertOk();

        $notificacion = Notificacion::where('type', 'firma_solicitada')
            ->where('notifiable_type', VentanillaRadicaReci::class)
            ->where('notifiable_id', $this->radicado->id)->sole();
        $this->assertSame($this->firmante->id, $notificacion->user_id);
        $this->assertSame("/mi-bandeja/recibidos?detail={$this->radicado->id}", $notificacion->data['url']);
        $this->assertSame('pendiente', $this->radicado->fresh()->estado_firma);
        $this->assertDatabaseMissing('firmas_eventos', [
            'documentable_type' => VentanillaRadicaReci::class,
            'documentable_id' => $this->radicado->id,
        ]);
    }

    public function test_radicador_puede_solicitar_firma_con_otp_sin_firmar_el_radicado(): void
    {
        $radicador = User::factory()->create(['estado' => 1]);
        $this->radicado->update([
            'usuario_crea' => $radicador->id,
            'archivo_digital' => 'm06/pendiente-otp.pdf',
            'estado_firma' => 'pendiente',
        ]);

        $this->actingAs($radicador)->postJson(self::URL.'/solicitar-otp', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => $this->radicado->id,
            'accion' => 'solicitar_firma',
        ])->assertOk();

        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otp, $radicador) {
            $otp = (string) $mail->otp;

            return $mail->hasTo($radicador->email);
        });

        $this->actingAs($radicador)->postJson(self::URL.'/solicitar-firma', [
            'documentable_id' => $this->radicado->id,
            'metodo' => 'otp',
            'otp' => $otp,
        ])->assertOk();

        $this->assertSame($this->firmante->id, Notificacion::where('type', 'firma_solicitada')
            ->where('notifiable_id', $this->radicado->id)->sole()->user_id);
        $this->assertDatabaseMissing('firmas_eventos', [
            'documentable_type' => VentanillaRadicaReci::class,
            'documentable_id' => $this->radicado->id,
        ]);
    }
}
