<?php

namespace Tests\Feature\Auth;

use App\Events\NotificationPushed;
use App\Mail\NuevoInicioSesionMail;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\ControlAcceso\UsersSession;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Auth\TwoFactorToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * SES-003: una sesión concurrente desde otra IP solo quedaba en el log, y el login con 2FA ni siquiera
 * la detectaba. Ahora se avisa al titular (notificación en app y, si lo eligió, correo).
 */
class SesionConcurrenteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        Event::fake([NotificationPushed::class]);

        $this->user = User::factory()->create(['estado' => 1, 'password' => bcrypt('Secreta.123')]);
    }

    private function sesionActivaDesde(string $ip): void
    {
        UsersSession::create([
            'user_id' => $this->user->id, 'ip_address' => $ip, 'user_agent' => 'PHPUnit',
            'last_login_at' => now(), 'is_active' => true,
        ]);
    }

    private function login()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/login', ['email' => $this->user->email, 'password' => 'Secreta.123']);
    }

    private function notificaciones(): int
    {
        return Notificacion::where('user_id', $this->user->id)->where('type', 'seguridad.sesion_concurrente')->count();
    }

    public function test_login_con_sesion_activa_en_otra_ip_notifica_al_titular(): void
    {
        $this->sesionActivaDesde('10.0.0.1');

        $this->login()->assertOk();

        $this->assertSame(1, $this->notificaciones());
        Mail::assertNothingSent(); // el correo es opcional y por defecto está apagado
    }

    public function test_con_preferencia_de_correo_tambien_envia_email(): void
    {
        UserNotificationSetting::create(['user_id' => $this->user->id, 'new_browser_login' => false, 'new_browser_login_email' => true]);
        $this->sesionActivaDesde('10.0.0.1');

        $this->login()->assertOk();

        $this->assertSame(0, $this->notificaciones());
        Mail::assertSent(NuevoInicioSesionMail::class, fn ($m) => $m->hasTo($this->user->email) && $m->ip === '10.0.0.2');
    }

    public function test_sin_sesion_en_otra_ip_no_notifica(): void
    {
        $this->sesionActivaDesde('10.0.0.2');

        $this->login()->assertOk();

        $this->assertSame(0, $this->notificaciones());
    }

    public function test_el_login_con_2fa_tambien_detecta_la_sesion_concurrente(): void
    {
        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            $this->markTestSkipped('La BD de pruebas no tiene las columnas de 2FA');
        }

        $google2fa = new Google2FA;
        $secreto = $google2fa->generateSecretKey();
        $this->user->forceFill(['two_factor_secret' => $secreto, 'two_factor_confirmed_at' => now()])->save();
        $this->sesionActivaDesde('10.0.0.1');

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/2fa/verify', ['two_factor_token' => TwoFactorToken::generate($this->user->id), 'code' => $google2fa->getCurrentOtp($secreto)])
            ->assertOk();

        $this->assertSame(1, $this->notificaciones());
    }
}
