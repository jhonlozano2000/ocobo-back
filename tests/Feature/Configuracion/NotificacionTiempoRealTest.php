<?php

namespace Tests\Feature\Configuracion;

use App\Events\NotificationPushed;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * M18-01: solo 5 de los puntos que crean notificaciones emitían NotificationPushed;
 * ahora lo hace el modelo al confirmarse la transacción.
 */
class NotificacionTiempoRealTest extends TestCase
{
    use DatabaseTransactions;

    private function crear(User $user): Notificacion
    {
        return Notificacion::create([
            'user_id' => $user->id, 'type' => 'asignacion_firmante', 'title' => 'Prueba', 'message' => 'Mensaje',
        ]);
    }

    public function test_toda_notificacion_creada_se_emite_tras_el_commit(): void
    {
        Event::fake([NotificationPushed::class]);
        $user = User::factory()->create(['estado' => 1]);

        DB::transaction(function () use ($user) {
            $this->crear($user);
            Event::assertNotDispatched(NotificationPushed::class);
        });

        Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $e) => $e->broadcastOn()->name === 'private-user.'.$user->id);
    }

    public function test_un_fallo_del_broadcaster_no_impide_crearla(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        Event::listen(NotificationPushed::class, fn () => throw new \RuntimeException('Reverb caído'));

        $notificacion = $this->crear($user);

        $this->assertDatabaseHas('notifications', ['id' => $notificacion->id]);
    }
}
