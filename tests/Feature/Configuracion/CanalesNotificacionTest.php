<?php

namespace Tests\Feature\Configuracion;

use App\Events\NotificationPushed;
use App\Helpers\CalendarioHelper;
use App\Jobs\EnviarCorreoNotificacion;
use App\Mail\NotificacionMail;
use App\Mail\RadicadoNotification;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M16-19: preferencias por categoría y canal (app = campana, email = correo, browser = aviso emergente),
 * correo genérico de «Nuevas para ti» y la categoría «Vencimientos» del comando alertas:vencimiento.
 */
class CanalesNotificacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['estado' => 1]);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function notificar(string $tipo, array $data = []): Notificacion
    {
        return Notificacion::create([
            'user_id' => $this->user->id, 'type' => $tipo, 'title' => 'Prueba', 'message' => 'Mensaje', 'data' => $data,
        ]);
    }

    private function preferencias(array $valores): void
    {
        UserNotificationSetting::create(['user_id' => $this->user->id] + $valores);
    }

    public function test_muestra_las_cuatro_categorias_con_sus_canales_por_defecto(): void
    {
        $this->actingAs($this->user)->getJson('/api/control-acceso/users/notification-settings')
            ->assertOk()
            ->assertExactJsonStructure(['status', 'success', 'message', 'data'])
            ->assertJsonPath('data', [
                'new_for_you' => ['app' => true, 'email' => false, 'browser' => true],
                'vencimientos' => ['app' => true, 'email' => true, 'browser' => true],
                'account_activity' => ['app' => true, 'browser' => true],
                'new_browser_login' => ['app' => true, 'email' => false, 'browser' => false],
            ]);
    }

    public function test_actualiza_solo_los_canales_enviados(): void
    {
        $this->actingAs($this->user)->putJson('/api/control-acceso/users/notification-settings', [
            'vencimientos' => ['email' => false],
            'new_for_you' => ['email' => true, 'browser' => false],
        ])->assertOk()
            ->assertJsonPath('data.vencimientos', ['app' => true, 'email' => false, 'browser' => true])
            ->assertJsonPath('data.new_for_you', ['app' => true, 'email' => true, 'browser' => false]);

        $fila = UserNotificationSetting::forUser($this->user->id);
        $this->assertFalse($fila->vencimientos_email);
        $this->assertTrue($fila->new_for_you_email);
        $this->assertFalse($fila->new_for_you_browser);
        $this->assertTrue($fila->vencimientos);
    }

    public function test_rechaza_valores_no_booleanos(): void
    {
        $this->actingAs($this->user)->putJson('/api/control-acceso/users/notification-settings', [
            'vencimientos' => ['email' => 'quizas'],
        ])->assertStatus(422)->assertJsonValidationErrors('vencimientos.email');
    }

    public function test_administracion_de_un_usuario_inexistente_responde_404(): void
    {
        $admin = User::factory()->create(['estado' => 1]);
        $admin->givePermissionTo(Permission::findOrCreate('Control de acceso - Usuarios -> Mostrar', 'web'));

        $this->actingAs($admin)->getJson('/api/control-acceso/users/999999999/notification-settings')
            ->assertNotFound();
    }

    public function test_el_correo_generico_solo_sale_para_nuevas_para_ti_con_el_correo_activo(): void
    {
        Bus::fake([EnviarCorreoNotificacion::class]);

        // Por defecto el correo de «Nuevas para ti» está apagado
        $this->notificar('asignacion_responsable');
        Bus::assertNotDispatched(EnviarCorreoNotificacion::class);

        UserNotificationSetting::create(['user_id' => $this->user->id, 'new_for_you_email' => true]);

        $nueva = $this->notificar('asignacion_responsable');
        $this->notificar('cambio_estado');
        $this->notificar('integridad_archivo');
        $this->notificar(Notificacion::TIPO_VENCIMIENTO);

        Bus::assertDispatchedTimes(EnviarCorreoNotificacion::class, 1);
        Bus::assertDispatched(EnviarCorreoNotificacion::class, fn ($job) => $job->notificacionId === $nueva->id);
    }

    public function test_el_aviso_emergente_sigue_la_preferencia_del_navegador(): void
    {
        Event::fake([NotificationPushed::class]);
        $this->preferencias(['new_for_you_browser' => false, 'account_activity_browser' => true]);

        $nueva = $this->notificar('asignacion_responsable');
        $actividad = $this->notificar('cambio_estado');
        $obligatoria = $this->notificar('integridad_archivo');

        $emergente = fn (Notificacion $n) => Event::dispatched(NotificationPushed::class)
            ->first(fn ($args) => $args[0]->notification->id === $n->id)[0]->emergente;

        $this->assertFalse($emergente($nueva));
        $this->assertTrue($emergente($actividad));
        $this->assertTrue($emergente($obligatoria));
    }

    public function test_las_alertas_obligatorias_no_tienen_canal_de_correo(): void
    {
        $alerta = new Notificacion(['type' => 'integridad_archivo']);

        $this->assertTrue($alerta->canalActivo('app', null));
        $this->assertTrue($alerta->canalActivo('browser', null));
        $this->assertFalse($alerta->canalActivo('email', null));
    }

    public function test_la_ruta_del_front_sale_de_los_datos_de_la_notificacion(): void
    {
        $ruta = fn (string $tipo, array $data) => (new Notificacion(['type' => $tipo, 'data' => $data]))->rutaFront();

        $this->assertSame('/archivo/x', $ruta('cambio_estado', ['url' => '/archivo/x', 'radica_reci_id' => 5]));
        $this->assertSame('/ventanilla-unica/radicar-correspondencia-recibida?detail=5', $ruta('vencimiento_proximo', ['radica_reci_id' => 5]));
        $this->assertSame('/ventanilla-unica/radicar-correspondencia-enviada?detail=7', $ruta('vencimiento_proximo', ['radica_enviado_id' => 7]));
        $this->assertSame('/mi-bandeja/tareas', $ruta('tarea.asignada', []));
        $this->assertSame('/notificaciones', $ruta('cambio_estado', []));
    }

    public function test_el_correo_generico_enlaza_la_notificacion_en_el_front(): void
    {
        $notificacion = $this->notificar('asignacion_responsable', ['radica_reci_id' => 12]);

        $html = (new NotificacionMail($notificacion->load('user')))->render();

        $this->assertStringContainsString('/es/ventanilla-unica/radicar-correspondencia-recibida?detail=12', $html);
        $this->assertStringContainsString('Mensaje', $html);
    }

    /**
     * Radicado recibido abierto con vencimiento hoy y el usuario como responsable.
     */
    private function radicadoPorVencer(string $estado = 'RECIBIDO'): int
    {
        Schema::disableForeignKeyConstraints();
        $userCargo = DB::table('users_cargos')->insertGetId([
            'user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => now()->toDateString(), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'VENC-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
            'estado_trabajo' => $estado, 'fec_venci' => CalendarioHelper::hoy()->toDateString(),
            'archivo_digital' => 'x.pdf', 'created_at' => now(),
        ]);
        DB::table('ventanilla_radica_reci_responsa')->insert([
            'radica_reci_id' => $id, 'users_cargos_id' => $userCargo, 'custodio' => 1,
        ]);

        return $id;
    }

    private function vencimientosDelUsuario()
    {
        return Notificacion::where('user_id', $this->user->id)->where('type', Notificacion::TIPO_VENCIMIENTO)->get();
    }

    public function test_el_comando_avisa_en_la_campana_y_por_correo_al_responsable(): void
    {
        Mail::fake();
        $abierto = $this->radicadoPorVencer();
        $this->radicadoPorVencer('TRAMITADO');
        $this->radicadoPorVencer('ANULADO');

        $this->artisan('alertas:vencimiento')->assertSuccessful();

        $avisos = $this->vencimientosDelUsuario();
        $this->assertCount(1, $avisos);
        $this->assertSame($abierto, $avisos->first()->data['radica_reci_id']);
        $this->assertSame('Radicado próximo a vencer', $avisos->first()->title);
        Mail::assertSent(RadicadoNotification::class, 1);
    }

    public function test_el_comando_respeta_las_preferencias_de_vencimientos(): void
    {
        Mail::fake();
        $this->preferencias(['vencimientos' => false, 'vencimientos_email' => false]);
        $this->radicadoPorVencer();

        $this->artisan('alertas:vencimiento')->assertSuccessful();

        $this->assertCount(0, $this->vencimientosDelUsuario());
        Mail::assertNothingSent();
    }

    public function test_una_pqrs_se_avisa_una_sola_vez(): void
    {
        Mail::fake();
        $radicado = $this->radicadoPorVencer();
        DB::table('ventanilla_pqrs')->insert([
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => 999999, 'estado_tramite' => 'Pendiente',
            'fecha_vencimiento' => CalendarioHelper::hoy()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('alertas:vencimiento')->assertSuccessful();

        $avisos = $this->vencimientosDelUsuario();
        $this->assertCount(1, $avisos);
        $this->assertSame('PQRS próxima a vencer', $avisos->first()->title);
        Mail::assertSent(RadicadoNotification::class, 1);
    }

    public function test_el_dry_run_no_notifica(): void
    {
        Mail::fake();
        $this->radicadoPorVencer();

        $this->artisan('alertas:vencimiento', ['--dry-run' => true])->assertSuccessful();

        $this->assertCount(0, $this->vencimientosDelUsuario());
        Mail::assertNothingSent();
    }
}
