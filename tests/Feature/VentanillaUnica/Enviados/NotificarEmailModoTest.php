<?php

namespace Tests\Feature\VentanillaUnica\Enviados;

use App\Models\User;
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

        // Catálogo de medio de recepción (requerido por ventanilla_radica_reci)
        $listaMedios = \App\Models\Configuracion\ConfigLista::create([
            'nombre' => 'Tipos de Recepción Notif',
            'cod' => 'L-MED-NT',
        ]);
        $this->medioRecepcion = \App\Models\Configuracion\ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo electrónico',
            'codigo' => 'CORR-NT',
            'estado' => 1,
        ]);

        // Catálogo de tipo PQRS (requerido por ventanilla_pqrs)
        $listaTipos = \App\Models\Configuracion\ConfigLista::create([
            'nombre' => 'Tipos de PQRS Notif',
            'cod' => 'L-TIPO-NT',
        ]);
        $this->tipoPqrs = \App\Models\Configuracion\ConfigListaDetalle::create([
            'lista_id' => $listaTipos->id,
            'nombre' => 'Peticion',
            'codigo' => 'PET-NT',
            'estado' => 1,
        ]);

        // SMTP "configurado" para que MailConfigHelper no bloquee (Mail::fake intercepta el envío)
        foreach ([
            ['correo_host', 'smtp.test.local'],
            ['correo_username', 'test@test.local'],
            ['correo_password', 'secret'],
            ['correo_from_address', 'no-reply@test.local'],
            ['correo_from_name', 'Test'],
        ] as [$clave, $valor]) {
            \App\Models\Configuracion\ConfigVarias::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
        }
    }

    protected $medioRecepcion;

    protected $tipoPqrs;

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
    public function rechaza_sin_modo(): void
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/ventanilla/pqrs/999/notificar-email', [
                'asunto' => 'Test',
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

    /**
     * Regresión: los responsables viven en el RADICADO, no en la PQRS.
     * Antes lanzaba 500 "Call to undefined relationship [responsables]".
     *
     * @test
     */
    public function envia_a_remitente_sin_explotar_por_responsables(): void
    {
        $tercero = \App\Models\Gestion\GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Remitente Notificar',
            'tipo' => 'Natural',
            'email' => 'remitente'.random_int(1, 99999).'@test.com',
            'notifica_email' => true,
        ]);

        $radicado = \App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci::create([
            'num_radicado' => 'NT-'.now()->format('Ymd').'-'.random_int(1, 99999),
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'tercero_id' => $tercero->id,
            'asunto' => 'Radicado para notificación',
        ]);

        $pqrs = \App\Models\VentanillaUnica\Comunes\VentanillaPqrs::create([
            'ventanilla_radica_reci_id' => $radicado->id,
            'tipo_pqrs_id' => $this->tipoPqrs->id,
            'fecha_vencimiento' => now()->addDays(15),
            'detalle_solicitud' => 'Solicitud de prueba',
        ]);

        $res = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$pqrs->id}/notificar-email", [
                'modo' => 'remitente',
            ]);

        $res->assertStatus(200);
        Mail::assertSent(\App\Mail\PqrsNotificacionEmail::class, function ($mail) use ($tercero) {
            return in_array($tercero->email, array_map(fn ($r) => $r['address'], $mail->to ?? []));
        });
    }

    /** @test */
    public function modo_responsables_sin_asignados_devuelve_422(): void
    {
        $radicado = \App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci::create([
            'num_radicado' => 'NR-'.now()->format('Ymd').'-'.random_int(1, 99999),
            'medio_recep_id' => $this->medioRecepcion->id,
            'usuario_crea' => $this->user->id,
            'asunto' => 'Sin responsables',
        ]);

        $pqrs = \App\Models\VentanillaUnica\Comunes\VentanillaPqrs::create([
            'ventanilla_radica_reci_id' => $radicado->id,
            'tipo_pqrs_id' => $this->tipoPqrs->id,
            'fecha_vencimiento' => now()->addDays(15),
            'detalle_solicitud' => 'Solicitud de prueba',
        ]);

        $res = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$pqrs->id}/notificar-email", [
                'modo' => 'responsables',
            ]);

        $res->assertStatus(422);
    }
}
