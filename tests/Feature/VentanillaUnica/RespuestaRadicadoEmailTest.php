<?php

namespace Tests\Feature\VentanillaUnica;

use App\Mail\RespuestaRadicadoMail;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\EmailRadicacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M05-01: la respuesta automática por correo solo adjunta el rótulo del radicado
 * creado a partir de ese mismo correo; nunca el de otro trámite con asunto o
 * remitente parecido.
 */
class RespuestaRadicadoEmailTest extends TestCase
{
    use DatabaseTransactions;

    protected VentanillaRadicaReci $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ventanilla_radica_reci', 'imap_uid')) {
            $this->markTestSkipped('BD de pruebas sin la migración add_imap_uid_to_ventanilla_radica_reci.');
        }

        $this->actingAs(User::factory()->create(['estado' => 1]));
        Mail::fake();

        $this->ajeno = $this->crearRadicado('M05-AJENO-0001', 'Solicitud', null);
    }

    private function crearRadicado(string $num, string $asunto, ?string $uid): VentanillaRadicaReci
    {
        // El medio de recepción no es relevante para estas pruebas
        Schema::disableForeignKeyConstraints();
        $radicado = VentanillaRadicaReci::create([
            'num_radicado' => $num,
            'asunto' => $asunto,
            'fechor_radica' => now(),
            'estado_trabajo' => 'RECIBIDO',
            'medio_recep_id' => 999999,
            'usuario_crea' => auth()->id(),
            'imap_uid' => $uid,
        ]);
        Schema::enableForeignKeyConstraints();

        return $radicado;
    }

    private function email(string $uid): array
    {
        return [
            'uid' => $uid,
            'asunto' => 'Solicitud',
            'remitente_email' => 'externo@example.com',
            'remitente_nombre' => 'Externo',
        ];
    }

    public function test_correo_no_radicado_no_recibe_rotulo_ajeno_con_mismo_asunto(): void
    {
        $enviado = app(EmailRadicacionService::class)->responderConRadicadoDirecto($this->email('m05-sin-radicar'), 'Hola');

        $this->assertFalse($enviado);
        Mail::assertNothingSent();
    }

    public function test_correo_radicado_recibe_su_propio_rotulo(): void
    {
        $this->crearRadicado('M05-PROPIO-0001', 'Otro asunto', 'm05-uid-propio');

        $enviado = app(EmailRadicacionService::class)->responderConRadicadoDirecto($this->email('m05-uid-propio'), 'Hola');

        $this->assertTrue($enviado);
        Mail::assertSent(RespuestaRadicadoMail::class, fn ($mail) => $mail->hasTo('externo@example.com')
            && $mail->radicado->num_radicado === 'M05-PROPIO-0001');
    }

    public function test_radicado_explicito_tiene_prioridad(): void
    {
        $propio = $this->crearRadicado('M05-PROPIO-0002', 'Solicitud', null);

        app(EmailRadicacionService::class)->responderConRadicadoDirecto($this->email('m05-otro'), 'Hola', $propio);

        Mail::assertSent(RespuestaRadicadoMail::class, fn ($mail) => $mail->radicado->is($propio));
    }
}
