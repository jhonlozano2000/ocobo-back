<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * OWASP A07: recuperar contraseña respondía "No encontramos una cuenta con ese correo" y el login
 * decía "cuenta desactivada" sin verificar la contraseña; ambos revelaban qué correos existen.
 */
class EnumeracionUsuariosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
    }

    public function test_recuperar_contrasena_responde_igual_exista_o_no_el_correo(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        $existe = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $noExiste = $this->postJson('/api/forgot-password', ['email' => 'nadie-'.uniqid().'@example.com'])->assertOk();

        $this->assertSame($existe->json('message'), $noExiste->json('message'));
        Mail::assertSent(PasswordResetMail::class, 1);
    }

    public function test_login_no_revela_cuenta_desactivada_sin_la_contrasena(): void
    {
        $user = User::factory()->create(['estado' => 0, 'password' => bcrypt('Secreta.123')]);

        $sinClave = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Otra.1234'])->assertUnauthorized();
        $noExiste = $this->postJson('/api/login', ['email' => 'nadie-'.uniqid().'@example.com', 'password' => 'Otra.1234']);

        $this->assertSame($noExiste->json('message'), $sinClave->json('message'));

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secreta.123'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Tu cuenta se encuentra desactivada.');
    }
}
