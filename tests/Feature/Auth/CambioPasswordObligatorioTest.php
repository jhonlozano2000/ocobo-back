<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ISO 27001 A.9.2.4: con una contraseña temporal (debe_cambiar_password) el usuario solo puede
 * ver su sesión, cambiar la contraseña o salir; al cambiarla recupera el acceso.
 */
class CambioPasswordObligatorioTest extends TestCase
{
    use DatabaseTransactions;

    private const TEMPORAL = 'Temporal.123';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->user = User::factory()->create(['estado' => 1, 'password' => Hash::make(self::TEMPORAL)]);
        $this->user->forceFill(['debe_cambiar_password' => true])->save();
    }

    public function test_bloquea_la_api_pero_deja_consultar_la_sesion(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->getJson('/api/2fa/setup')
            ->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');

        $this->getJson('/api/getme')
            ->assertOk()
            ->assertJsonPath('data.user.debe_cambiar_password', true);
    }

    public function test_cambiar_la_contrasena_libera_el_acceso(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->putJson('/api/control-acceso/user/changePassword', [
            'current_password' => self::TEMPORAL,
            'password' => 'Definitiva.456',
            'password_confirmation' => 'Definitiva.456',
        ])->assertOk();

        $this->assertFalse($this->user->fresh()->debe_cambiar_password);
        $this->getJson('/api/2fa/setup')->assertOk();
    }

    public function test_la_nueva_contrasena_debe_ser_distinta_de_la_temporal(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->putJson('/api/control-acceso/user/changePassword', [
            'current_password' => self::TEMPORAL,
            'password' => self::TEMPORAL,
            'password_confirmation' => self::TEMPORAL,
        ])->assertUnprocessable();

        $this->assertTrue($this->user->fresh()->debe_cambiar_password);
    }

    public function test_usuario_sin_contrasena_temporal_no_se_bloquea(): void
    {
        $this->user->forceFill(['debe_cambiar_password' => false])->save();

        $this->actingAs($this->user, 'sanctum')->getJson('/api/2fa/setup')->assertOk();
    }
}
