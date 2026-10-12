<?php

namespace Tests\Feature\VentanillaUnica;

use App\Mail\OtpFirmaMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M04-14: el OTP de firma de PQRS se guardaba en claro, sin límite de intentos.
 * M06-05: guardar-firma no exigía haber validado el OTP.
 */
class FirmaPqrsOtpTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $pqrs;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Mostrar', 'Firmar peticionario'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate("Radicar -> PQRSF -> {$accion}", 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0414-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        $this->pqrs = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => 1, 'fecha_vencimiento' => '2026-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function url(string $accion): string
    {
        return "/api/ventanilla/pqrs/{$this->pqrs}/{$accion}";
    }

    private function solicitarOtp(): string
    {
        $this->actingAs($this->user)->postJson($this->url('solicitar-otp-firma'))->assertOk();

        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        return $otp;
    }

    private function firmar()
    {
        return $this->actingAs($this->user)->postJson($this->url('guardar-firma'), ['firma_digital' => 'FIRMA-'.$this->pqrs]);
    }

    public function test_guardar_firma_sin_validar_el_otp_responde_403(): void
    {
        $this->solicitarOtp();

        $this->firmar()->assertForbidden();
        $this->assertDatabaseMissing('ventanilla_pqrs', ['id' => $this->pqrs, 'estado_firma' => 'firmada']);
    }

    public function test_flujo_completo_firma_y_la_validacion_es_de_un_solo_uso(): void
    {
        $otp = $this->solicitarOtp();

        $this->actingAs($this->user)->postJson($this->url('validar-otp-firma'), ['otp' => $otp])->assertOk();
        $this->firmar()->assertOk();
        $this->assertDatabaseHas('ventanilla_pqrs', ['id' => $this->pqrs, 'estado_firma' => 'firmada']);

        // El mismo OTP no se puede reutilizar
        $this->actingAs($this->user)->postJson($this->url('validar-otp-firma'), ['otp' => $otp])->assertForbidden();
    }

    public function test_el_otp_no_se_guarda_en_claro_y_se_invalida_tras_5_fallos(): void
    {
        // Se prueba el límite propio del OTP, no el rate limit de la ruta
        $this->withoutMiddleware(ThrottleRequests::class);
        $otp = $this->solicitarOtp();

        $guardado = Cache::get("otp_firma_pqrs_{$this->pqrs}_{$this->user->id}");
        $this->assertIsArray($guardado);
        $this->assertNotSame($otp, $guardado['hash']);

        $incorrecto = $otp === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)->postJson($this->url('validar-otp-firma'), ['otp' => $incorrecto])->assertForbidden();
        }

        $this->actingAs($this->user)->postJson($this->url('validar-otp-firma'), ['otp' => $otp])->assertForbidden();
    }
}
