<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M06-13: la firma masiva listaba firmas ya hechas, enviaba tipos que el back rechazaba y pedía
 * un OTP por documento con window.prompt. Ahora hay listado de pendientes y un OTP por lote.
 */
class FirmaMasivaLoteTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/api/transversal/firma-electronica';

    private User $firmante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        if (! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_internos_responsa LIKE ventanilla_radica_interno_responsa');
        }
        $this->firmante = User::factory()->create(['estado' => 1]);
    }

    private function interno(bool $firmado = false, ?User $firmante = null): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'M0613-'.uniqid(), 'clasifica_documen_id' => 1, 'usuario_crea' => $this->firmante->id,
            'archivo_digital' => 'm0613-'.uniqid().'.pdf', 'estado_firma' => 'pendiente',
        ]);
        DB::table('ventanilla_radica_internos_firmantes')->insert([
            'radica_interno_id' => $id, 'users_id' => ($firmante ?? $this->firmante)->id, 'firmado' => $firmado,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function lote(int ...$ids): array
    {
        return array_map(fn ($id) => ['documentable_type' => 'radicado_interno', 'documentable_id' => $id], $ids);
    }

    private function solicitarOtp(array $documentos): ?string
    {
        $this->actingAs($this->firmante)->postJson(self::BASE.'/solicitar-otp-masivo', ['documentos' => $documentos])->assertOk();

        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        return $otp;
    }

    public function test_pendientes_lista_solo_lo_que_falta_firmar(): void
    {
        $pendiente = $this->interno();
        $yaFirmado = $this->interno(firmado: true);
        $ajeno = $this->interno(firmante: User::factory()->create(['estado' => 1]));

        $ids = collect($this->actingAs($this->firmante)->getJson(self::BASE.'/pendientes')->assertOk()->json('data'))
            ->where('documentable_type', 'radicado_interno')->pluck('documentable_id');

        $this->assertContains($pendiente, $ids);
        $this->assertNotContains($yaFirmado, $ids);
        $this->assertNotContains($ajeno, $ids);
    }

    public function test_no_se_emite_otp_para_un_lote_con_documentos_ajenos(): void
    {
        $propio = $this->interno();
        $ajeno = $this->interno(firmante: User::factory()->create(['estado' => 1]));

        $this->actingAs($this->firmante)
            ->postJson(self::BASE.'/solicitar-otp-masivo', ['documentos' => $this->lote($propio, $ajeno)])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_el_otp_solo_sirve_para_el_lote_exacto_y_una_vez(): void
    {
        $a = $this->interno();
        $b = $this->interno();
        $otp = $this->solicitarOtp($this->lote($a));

        // Otro conjunto de documentos: el OTP no aplica
        $this->postJson(self::BASE.'/firmar-masivo', ['otp' => $otp, 'documentos' => $this->lote($a, $b)])->assertStatus(422);

        // El lote exacto pasa la validación del OTP (la firma del PDF falla aquí porque no hay archivo real)
        $this->postJson(self::BASE.'/firmar-masivo', ['otp' => $otp, 'documentos' => $this->lote($a)])
            ->assertOk()->assertJsonPath('data.resumen.total', 1);

        // Un solo uso
        $this->postJson(self::BASE.'/firmar-masivo', ['otp' => $otp, 'documentos' => $this->lote($a)])->assertStatus(422);
    }
}
