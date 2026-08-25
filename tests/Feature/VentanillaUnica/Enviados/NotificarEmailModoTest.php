<?php

namespace Tests\Feature\VentanillaUnica\Enviados;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests del modo de notificación PQRS (todos|responsables|remitente).
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-25
 */
class NotificarEmailModoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'Radicar -> PQRSF -> Listar']);
        Permission::firstOrCreate(['name' => 'Radicar -> PQRSF -> Notificar Email']);

        $rol = Role::firstOrCreate(['name' => 'Notificador Test']);
        $rol->givePermissionTo(['Radicar -> PQRSF -> Listar', 'Radicar -> PQRSF -> Notificar Email']);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);

        Mail::fake();
    }

    /** @test */
    public function rechaza_modo_invalido(): void
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/ventanilla/pqrs/999/notificar-email', [
                'modo' => 'jefe',
                'asunto' => 'Test',
                'mensaje' => 'Mensaje',
            ]);

        $res->assertStatus(422);
    }

    /** @test */
    public function rechaza_sin_asunto(): void
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/ventanilla/pqrs/999/notificar-email', [
                'modo' => 'todos',
                'mensaje' => 'Mensaje',
            ]);

        $res->assertStatus(422);
    }

    /** @test */
    public function rechaza_pqrs_inexistente_con_404(): void
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/ventanilla/pqrs/99999/notificar-email', [
                'modo' => 'todos',
                'asunto' => 'Test',
                'mensaje' => 'Mensaje',
            ]);

        $res->assertStatus(404);
    }
}
