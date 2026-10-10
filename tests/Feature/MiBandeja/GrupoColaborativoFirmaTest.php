<?php

namespace Tests\Feature\MiBandeja;

use App\Mail\OtpFirmaMail;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\Firma\FirmaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GrupoColaborativoFirmaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected VentanillaRadicaReci $radicado;

    protected MiBandejaTemp $grupo;

    protected MiBandejaTempGrupoFirmante $firmante;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Obtener o crear permisos necesarios
        Permission::findOrCreate('Mi Bandeja - Grupos Colaborativos -> Ver', 'web');
        Permission::findOrCreate('Mi Bandeja - Grupos Colaborativos -> Crear', 'web');
        Permission::findOrCreate('Mi Bandeja - Grupos Colaborativos -> Enviar Tramite', 'web');

        // Usuario para pruebas
        $this->user = User::factory()->create([
            'nombres' => 'Funcionario',
            'apellidos' => 'Firmante',
            'email' => 'firmante.test@ocobo.gov.co',
            'password' => Hash::make('Secret123*'),
        ]);

        $this->user->givePermissionTo([
            'Mi Bandeja - Grupos Colaborativos -> Ver',
            'Mi Bandeja - Grupos Colaborativos -> Crear',
            'Mi Bandeja - Grupos Colaborativos -> Enviar Tramite',
        ]);

        // Asegurar que exista un medio de recepción válido
        $medioRecep = ConfigListaDetalle::first();
        if (! $medioRecep) {
            $lista = ConfigLista::firstOrCreate(['cod' => 'TipRecep'], ['nombre' => 'Tipos de Recepción']);
            $medioRecep = ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'CORREO', 'nombre' => 'Correo']);
        }

        // Radicado recibido de prueba
        $this->radicado = VentanillaRadicaReci::first() ?? VentanillaRadicaReci::create([
            'num_radicado' => 'TEST-2026-0001',
            'asunto' => 'Radicado para prueba de firma colaborativa',
            'fechor_radica' => now(),
            'estado_trabajo' => 'en_tramite',
            'medio_recep_id' => $medioRecep->id,
            'usuario_crea' => $this->user->id,
        ]);

        // Grupo colaborativo de prueba
        $this->grupo = MiBandejaTemp::create([
            'nombre' => 'Grupo de Prueba Firma ISO 27001',
            'asunto' => 'Respuesta a radicado TEST-2026-0001',
            'radicado_id' => $this->radicado->id,
            'radicado_tipo' => 'recibido',
            'estado' => 'en_curso',
            'estado_grupo' => 'activo',
            'usua_crea_id' => $this->user->id,
        ]);

        // Asignar al usuario como firmante del grupo
        $this->firmante = MiBandejaTempGrupoFirmante::create([
            'grupo_id' => $this->grupo->id,
            'user_id' => $this->user->id,
            'orden_firma' => 1,
            'estado_tarea' => 'pendiente',
        ]);
    }

    /**
     * Prueba: Solicitar OTP envía el correo y retorna el email enmascarado.
     */
    public function test_solicitar_otp_envia_correo_al_firmante(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/firmantes/solicitar-otp");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        Mail::assertSent(OtpFirmaMail::class, function ($mail) {
            return $mail->hasTo('firmante.test@ocobo.gov.co');
        });
    }

    /**
     * Prueba: Firmar con contraseña incorrecta retorna HTTP 422.
     */
    public function test_firmar_con_password_incorrecta_retorna_422(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/firmantes/{$this->firmante->id}/firmar", [
                'metodo' => 'password',
                'password' => 'PasswordErronea999',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'La contraseña ingresada es incorrecta.');
    }

    /**
     * Prueba: Firmar con contraseña correcta actualiza la fecha de firma y crea el evento ISO 27001.
     */
    public function test_firmar_con_password_correcta_exitoso(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/firmantes/{$this->firmante->id}/firmar", [
                'metodo' => 'password',
                'password' => 'Secret123*',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->firmante->refresh();
        $this->assertNotNull($this->firmante->fechor_firmado);
        $this->assertEquals('cumplido', $this->firmante->estado_tarea);

        // Verificar registro de auditoría ISO 27001
        $this->assertDatabaseHas('firmas_eventos', [
            'documentable_id' => $this->grupo->id,
            'documentable_type' => MiBandejaTemp::class,
            'user_id' => $this->user->id,
            'otp_utilizado' => 'PASSWORD',
        ]);
    }

    /**
     * Prueba: Firmar con OTP válido actualiza la fecha de firma y crea el evento ISO 27001.
     */
    public function test_firmar_con_otp_valido_exitoso(): void
    {
        // Generar OTP con FirmaService
        $firmaService = app(FirmaService::class);
        $firmaService->generarYEnviarOtpParaEmail(
            $this->user->email,
            'Funcionario Firmante',
            'grupo_colaborativo',
            $this->grupo->id,
            $this->user->id
        );

        // Obtener el OTP del correo enviado (en caché solo se guarda su hash)
        $otpGenerado = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otpGenerado) {
            $otpGenerado = (string) $mail->otp;

            return true;
        });
        $this->assertNotNull($otpGenerado);

        $response = $this->actingAs($this->user)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/firmantes/{$this->firmante->id}/firmar", [
                'metodo' => 'otp',
                'otp' => (string) $otpGenerado,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->firmante->refresh();
        $this->assertNotNull($this->firmante->fechor_firmado);

        // Verificar evento de auditoría ISO 27001 con el OTP utilizado
        $this->assertDatabaseHas('firmas_eventos', [
            'documentable_id' => $this->grupo->id,
            'documentable_type' => MiBandejaTemp::class,
            'user_id' => $this->user->id,
            'otp_utilizado' => '***'.substr((string) $otpGenerado, -3),
        ]);
    }

    /**
     * Prueba: Marcar terminado vía MiBandejaTempController requiere método de verificación si es firmante.
     */
    public function test_marcar_terminado_requiere_verificacion_para_firmante(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/marcar-terminado", []);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }
}
