<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Firma de un documento de Mis Documentos con grupo colaborativo: el firmante firma al marcar cumplida su tarea
 * (OTP o contraseña) y eso sella el contenido del editor; no hay una segunda firma aparte desde el editor.
 */
class FirmaGrupoDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private const URL_GRUPOS = '/api/mi-bandeja/grupos-colaborativos';

    private User $creador;

    private User $revisor;

    private User $firmante;

    private int $documento;

    private int $grupo;

    private int $firmanteId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $permisos = array_map(fn ($p) => "Mi Bandeja - Grupos Colaborativos -> {$p}", ['Ver', 'Crear', 'Gestionar Miembros']);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->creador = User::factory()->create(['estado' => 1]);
        $this->revisor = User::factory()->create(['estado' => 1]);
        $this->firmante = User::factory()->create(['estado' => 1, 'nombres' => 'Fabio', 'apellidos' => 'Díaz']);
        $this->creador->givePermissionTo($permisos);
        $this->revisor->givePermissionTo($permisos[0]);
        $this->firmante->givePermissionTo($permisos[0]);

        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->creador->id, 'titulo' => 'Oficio', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grupo = $this->actingAs($this->creador)->postJson(self::URL_GRUPOS, [
            'nombre' => 'Oficio', 'asunto' => 'Respuesta', 'documento_id' => $this->documento,
        ])->assertCreated()->json('data.id');
        $this->postJson(self::URL_GRUPOS."/{$this->grupo}/revisores", ['user_id' => $this->revisor->id])->assertCreated();
        $this->firmanteId = $this->postJson(self::URL_GRUPOS."/{$this->grupo}/firmantes", [
            'user_id' => $this->firmante->id, 'orden_firma' => 1,
        ])->assertCreated()->json('data.id');
    }

    private function conContenido(): void
    {
        $contenido = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hola']]]]];
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $this->documento, 'contenido_yjs' => json_encode($contenido),
            'hash_contenido' => hash('sha256', json_encode($contenido)), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function firmas(): Builder
    {
        return FirmaEvento::where('documentable_id', $this->documento)->where('user_id', $this->firmante->id);
    }

    private function cumplirComoFirmante(): TestResponse
    {
        return $this->actingAs($this->firmante)->postJson(self::URL_GRUPOS."/{$this->grupo}/marcar-terminado", [
            'metodo' => 'password', 'password' => 'password',
        ]);
    }

    public function test_el_cumplido_del_firmante_firma_el_documento_del_editor(): void
    {
        $this->conContenido();

        // Desde el editor no se firma aparte: la firma es el cumplido del grupo
        $this->actingAs($this->firmante)->getJson(self::URL."/{$this->documento}/firmas")
            ->assertOk()
            ->assertJsonPath('data.firma_en_grupo', true)
            ->assertJsonPath('data.puede_firmar', false);
        $this->postJson(self::URL."/{$this->documento}/firmas/solicitar-otp")->assertUnprocessable();

        $this->actingAs($this->revisor)->postJson(self::URL_GRUPOS."/{$this->grupo}/marcar-terminado")->assertOk();
        $this->cumplirComoFirmante()
            ->assertOk()
            ->assertJsonPath('data.rol', 'firmante')
            ->assertJsonPath('data.nuevo_estado', 'listo_envio');

        $this->assertSame('PASSWORD', $this->firmas()->value('otp_utilizado'));
        $this->assertSame('firmado', DB::table('mi_bandeja_temp_reci_documentos')->where('id', $this->documento)->value('estado'));
        $this->getJson(self::URL."/{$this->documento}/firmas")
            ->assertOk()
            ->assertJsonPath('data.protegido', true)
            ->assertJsonPath('data.firmas.0.usuario', 'Fabio Díaz')
            ->assertJsonCount(0, 'data.pendientes');

        // Retirar las firmas para corregir deja al firmante pendiente y el grupo vuelve a borrador
        $this->actingAs($this->creador)->deleteJson(self::URL."/{$this->documento}/firmas")->assertOk();
        $firmante = DB::table('mi_bandeja_temp_grupo_firmantes')->where('id', $this->firmanteId)->first();
        $this->assertSame('pendiente', $firmante->estado_tarea);
        $this->assertNull($firmante->fechor_firmado);
        $this->assertSame('borrador', DB::table('mi_bandeja_temp')->where('id', $this->grupo)->value('estado'));
    }

    public function test_el_endpoint_firmar_del_firmante_tambien_sella_el_editor(): void
    {
        $this->conContenido();

        $this->actingAs($this->firmante)->postJson(self::URL_GRUPOS."/{$this->grupo}/firmantes/{$this->firmanteId}/firmar", [
            'metodo' => 'password', 'password' => 'password',
        ])->assertOk();

        $this->assertSame(1, $this->firmas()->count());
        $this->assertSame('firmado', DB::table('mi_bandeja_temp_reci_documentos')->where('id', $this->documento)->value('estado'));
    }

    public function test_un_documento_vacio_no_se_firma_y_el_firmante_sigue_pendiente(): void
    {
        $this->cumplirComoFirmante()->assertUnprocessable();

        $this->assertSame(0, $this->firmas()->count());
        $this->assertSame('pendiente', DB::table('mi_bandeja_temp_grupo_firmantes')->where('id', $this->firmanteId)->value('estado_tarea'));
    }
}
