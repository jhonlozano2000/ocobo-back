<?php

namespace Tests\Feature\MiBandeja;

use App\Mail\OtpFirmaMail;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Firma con OTP en Mis Documentos: la firma sella el hash del contenido y protege el documento (nadie lo
 * edita); al retirar las firmas vuelve a borrador y las firmas anteriores dejan de ser vigentes.
 */
class FirmaDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $yo;

    private int $documento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $this->yo = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Ruiz', 'email' => 'ana.firma@prueba.co']);
        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Respuesta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $contenido = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hola']]]]];
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $this->documento, 'contenido_yjs' => json_encode($contenido),
            'hash_contenido' => hash('sha256', json_encode($contenido)), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function url(string $resto = ''): string
    {
        return self::URL.'/'.$this->documento.$resto;
    }

    private function pedirOtp(User $user): string
    {
        $this->actingAs($user)->postJson($this->url('/firmas/solicitar-otp'))
            ->assertOk()
            ->assertJsonPath('data.correo', 'a***@prueba.co');

        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function (OtpFirmaMail $mail) use (&$otp) {
            $otp = (string) $mail->otp;

            return true;
        });

        return $otp;
    }

    public function test_el_creador_firma_con_otp_y_el_documento_queda_protegido(): void
    {
        $this->actingAs($this->yo)->getJson($this->url('/firmas'))
            ->assertOk()
            ->assertJsonPath('data.puede_firmar', true)
            ->assertJsonPath('data.protegido', false)
            ->assertJsonPath('data.pendientes.0.nombre', 'Ana Ruiz');

        $otp = $this->pedirOtp($this->yo);
        $otroCodigo = $otp === '000000' ? '111111' : '000000';
        $this->postJson($this->url('/firmas'), ['otp' => $otroCodigo])->assertStatus(422)->assertJsonValidationErrors('otp');

        $otp = $this->pedirOtp($this->yo);
        $this->postJson($this->url('/firmas'), ['otp' => $otp])
            ->assertOk()
            ->assertJsonPath('data.protegido', true)
            ->assertJsonPath('data.puede_firmar', false)
            ->assertJsonPath('data.firmas.0.usuario', 'Ana Ruiz')
            ->assertJsonCount(0, 'data.pendientes');

        $this->assertSame('firmado', DB::table('mi_bandeja_temp_reci_documentos')->where('id', $this->documento)->value('estado'));
        $this->assertSame(1, FirmaEvento::where('documentable_id', $this->documento)->count());

        // Protegido: ni el contenido ni los datos se cambian
        $this->postJson($this->url('/sincronizar'), ['contenido' => ['type' => 'doc', 'content' => []]])->assertForbidden();
        $this->putJson($this->url(), ['titulo' => 'Otro título'])->assertForbidden();
    }

    public function test_retirar_las_firmas_vuelve_a_borrador_y_deja_de_ser_vigente(): void
    {
        $this->actingAs($this->yo)->postJson($this->url('/firmas'), ['otp' => $this->pedirOtp($this->yo)])->assertOk();

        $this->deleteJson($this->url('/firmas'))
            ->assertOk()
            ->assertJsonPath('data.protegido', false)
            ->assertJsonCount(0, 'data.firmas')
            ->assertJsonPath('data.puede_firmar', true);

        $this->postJson($this->url('/sincronizar'), ['contenido' => ['type' => 'doc', 'content' => []]])->assertOk();
        // El evento de firma es inmutable: queda como historial
        $this->assertSame(1, FirmaEvento::where('documentable_id', $this->documento)->count());
    }

    public function test_solo_firman_los_firmantes_designados_y_el_estado_firmado_no_se_pone_a_mano(): void
    {
        $firmante = User::factory()->create(['estado' => 1, 'email' => 'a.firmante@prueba.co']);
        $proyector = User::factory()->create(['estado' => 1]);
        DB::table('mi_bandeja_temp_reci_usuarios')->insert([
            ['documento_id' => $this->documento, 'user_id' => $firmante->id, 'rol' => 'firmante', 'created_at' => now(), 'updated_at' => now()],
            ['documento_id' => $this->documento, 'user_id' => $proyector->id, 'rol' => 'proyector', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Con firmantes designados, el creador ya no firma; un proyector tampoco
        $this->actingAs($this->yo)->postJson($this->url('/firmas/solicitar-otp'))->assertForbidden();
        $this->actingAs($proyector)->postJson($this->url('/firmas/solicitar-otp'))->assertForbidden();

        $this->actingAs($this->yo)->putJson($this->url(), ['titulo' => 'Respuesta', 'estado' => 'firmado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');

        $this->actingAs($firmante)->postJson($this->url('/firmas'), ['otp' => $this->pedirOtp($firmante)])
            ->assertOk()
            ->assertJsonPath('data.protegido', true);
    }
}
