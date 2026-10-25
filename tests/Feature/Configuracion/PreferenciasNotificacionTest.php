<?php

namespace Tests\Feature\Configuracion;

use App\Events\NotificationPushed;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * M16-19: las preferencias de notificación (canal app) solo se respetaban en 2 de los ~38 puntos que
 * crean notificaciones; ahora las aplica el modelo. Las alertas de control de archivo llegan siempre.
 */
class PreferenciasNotificacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['estado' => 1]);
    }

    private function notificar(string $tipo): Notificacion
    {
        return Notificacion::create([
            'user_id' => $this->user->id, 'type' => $tipo, 'title' => 'Prueba', 'message' => 'Mensaje',
        ]);
    }

    private function preferencias(array $valores): void
    {
        UserNotificationSetting::create(['user_id' => $this->user->id] + $valores);
    }

    private function recibidas(): array
    {
        return Notificacion::where('user_id', $this->user->id)->pluck('type')->sort()->values()->all();
    }

    public function test_sin_preferencias_guardadas_llegan_todas(): void
    {
        $this->notificar('asignacion_firmante');
        $this->notificar('tarea.completada');
        $this->notificar('seguridad.sesion_concurrente');

        $this->assertCount(3, $this->recibidas());
    }

    public function test_apagar_nuevas_para_ti_silencia_las_asignaciones_y_no_las_emite(): void
    {
        Event::fake([NotificationPushed::class]);
        $this->preferencias(['new_for_you' => false, 'account_activity' => true]);

        foreach (['asignacion_responsable', 'asignacion_proyector', 'asignacion_firmante', 'tarea.asignada'] as $tipo) {
            $omitida = $this->notificar($tipo);
            $this->assertFalse($omitida->exists, $tipo);
        }
        $this->notificar('tarea.completada');

        $this->assertSame(['tarea.completada'], $this->recibidas());
        Event::assertDispatchedTimes(NotificationPushed::class, 1);
    }

    public function test_apagar_actividad_de_cuenta_silencia_los_avisos_operativos(): void
    {
        $this->preferencias(['new_for_you' => true, 'account_activity' => false]);

        foreach (['tarea.completada', 'tarea.vencida', 'trd.aprobada', 'prestamo_respuesta', 'transferencia_recibida'] as $tipo) {
            $this->notificar($tipo);
        }
        $this->notificar('asignacion_firmante');

        $this->assertSame(['asignacion_firmante'], $this->recibidas());
    }

    public function test_las_alertas_de_control_de_archivo_llegan_aunque_todo_este_apagado(): void
    {
        $this->preferencias(['new_for_you' => false, 'account_activity' => false, 'new_browser_login' => false]);

        foreach (Notificacion::TIPOS_OBLIGATORIOS as $tipo) {
            $this->notificar($tipo);
        }
        $this->notificar('seguridad.sesion_concurrente');

        $this->assertSame(collect(Notificacion::TIPOS_OBLIGATORIOS)->sort()->values()->all(), $this->recibidas());
    }

    public function test_cada_tipo_se_asigna_a_su_preferencia(): void
    {
        $this->assertSame('new_for_you', Notificacion::preferenciaDe('asignacion_responsable'));
        $this->assertSame('new_browser_login', Notificacion::preferenciaDe('seguridad.sesion_concurrente'));
        $this->assertSame('account_activity', Notificacion::preferenciaDe('instancia.completada'));
        $this->assertNull(Notificacion::preferenciaDe('integridad_archivo'));
    }
}
