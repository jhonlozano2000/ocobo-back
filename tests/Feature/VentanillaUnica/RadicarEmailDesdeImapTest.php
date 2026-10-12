<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Services\VentanillaUnica\EmailRadicacionService;
use App\Services\VentanillaUnica\ImapEmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M05-04: radicar() armaba los datos del correo con lo que enviaba el cliente (y como el
 * FormRequest no los validaba, en la práctica llegaban vacíos). Ahora se leen del servidor IMAP.
 */
class RadicarEmailDesdeImapTest extends TestCase
{
    use DatabaseTransactions;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Recibida -> Crear', 'web'));
        $this->actingAs($user);

        // Solo para pasar las reglas `exists` del FormRequest; la radicación está simulada
        Schema::disableForeignKeyConstraints();
        $clasificacion = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $user->id, 'tipo' => 'TipoDocumento']);
        $lista = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 1, 'nombre' => 'M05-04']);
        Schema::enableForeignKeyConstraints();

        $this->payload = [
            'tipo_radicado' => 'recibido', 'clasifica_documen_id' => $clasificacion, 'medio_recep_id' => $lista,
            'tipo_solicitud_id' => $lista, 'fec_documento' => now()->toDateString(),
            'email_asunto' => 'Asunto falsificado', 'email_remitente_email' => 'falso@example.com',
        ];
    }

    public function test_los_datos_del_correo_salen_del_servidor_imap(): void
    {
        $this->mock(ImapEmailService::class)->shouldReceive('getMessageDetails')->with('77')->andReturn([
            'uid' => '77', 'asunto' => 'Asunto real', 'remitente_email' => 'real@example.com', 'remitente_nombre' => 'Real',
            'fecha_correo' => now()->toDateTimeString(), 'body_text' => 'Cuerpo', 'body_html' => '',
            'tiene_adjuntos' => false, 'adjuntos_info' => [],
        ]);
        $this->mock(EmailRadicacionService::class)->shouldReceive('radicarFromEmailData')->once()
            ->withArgs(fn ($email) => $email['asunto'] === 'Asunto real' && $email['remitente_email'] === 'real@example.com' && $email['uid'] === '77')
            ->andReturn(['num_radicado' => 'X']);

        $this->postJson('/api/ventanilla/email-radicados/77/radicar', $this->payload)->assertCreated();
    }

    public function test_correo_inexistente_en_imap_no_se_radica(): void
    {
        $this->mock(ImapEmailService::class)->shouldReceive('getMessageDetails')->andReturn(null);
        $this->mock(EmailRadicacionService::class)->shouldNotReceive('radicarFromEmailData');

        $this->postJson('/api/ventanilla/email-radicados/404/radicar', $this->payload)->assertNotFound();
    }
}
