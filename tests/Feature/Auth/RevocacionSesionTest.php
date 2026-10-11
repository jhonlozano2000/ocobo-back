<?php

namespace Tests\Feature\Auth;

use App\Models\ControlAcceso\UsersSession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SES-001: "cerrar sesión" en dispositivos recientes solo borraba la fila de auditoría.
 * SES-002: un usuario desactivado conservaba su sesión viva.
 */
class RevocacionSesionTest extends TestCase
{
    use DatabaseTransactions;

    private function fila(User $user): UsersSession
    {
        return UsersSession::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'last_login_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_usuario_desactivado_pierde_la_sesion(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $this->actingAs($user)->getJson('/api/getme')->assertOk();

        $user->update(['estado' => 0]);

        $this->actingAs($user->fresh())->getJson('/api/getme')->assertUnauthorized();
    }

    public function test_sesion_revocada_deja_de_valer(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $otroDispositivo = $this->fila($user);
        $actual = $this->fila($user);

        $this->actingAs($user)->withSession([UsersSession::CLAVE_SESION => $actual->id])
            ->deleteJson('/api/control-acceso/user/sessions/'.$otroDispositivo->id)
            ->assertOk();

        $this->assertFalse($otroDispositivo->fresh()->is_active);
        $this->assertNotNull($otroDispositivo->fresh()->logout_at);

        // La sesión actual sigue válida; la del dispositivo revocado ya no autentica
        $this->actingAs($user)->withSession([UsersSession::CLAVE_SESION => $actual->id])
            ->getJson('/api/getme')->assertOk();
        $this->actingAs($user)->withSession([UsersSession::CLAVE_SESION => $otroDispositivo->id])
            ->getJson('/api/getme')->assertUnauthorized();
    }

    public function test_logout_cierra_la_fila_de_la_sesion(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $fila = $this->fila($user);

        $this->actingAs($user)->withSession([UsersSession::CLAVE_SESION => $fila->id])
            ->postJson('/api/logout')->assertOk();

        $this->assertFalse($fila->fresh()->is_active);
    }
}
