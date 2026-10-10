<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\Firma\FirmaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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

    public function test_flujo_legado_solo_acepta_tipos_de_la_lista_blanca(): void
    {
        $legado = '/api/transversal/firma-electronica';

        // M06-04: antes se instanciaba 'App\Models\'.documento_type (cualquier modelo)
        $this->actingAs($this->firmante)
            ->postJson($legado.'/firmar', ['otp' => '123456', 'documento_type' => 'User', 'documento_id' => $this->ajeno->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documento_type');

        $this->actingAs($this->ajeno)
            ->postJson($legado.'/solicitar-otp', ['documento_type' => 'radicado_recibido', 'documento_id' => $this->radicado->id])
            ->assertForbidden();

        $this->actingAs($this->firmante)
            ->postJson($legado.'/solicitar-otp', ['documento_type' => 'radicado_recibido', 'documento_id' => $this->radicado->id])
            ->assertOk();
        Mail::assertSent(OtpFirmaMail::class, fn ($mail) => $mail->hasTo($this->firmante->email));
    }
}
