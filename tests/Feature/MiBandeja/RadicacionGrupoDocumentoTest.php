<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Radicación de un grupo de un documento del editor: cuando todos cumplieron y firmaron aparece en Pendientes por
 * radicar de Ventanilla (enviado), como un grupo con plantilla, o el creador o un firmante lo radica como interno; el
 * archivo digital es el PDF del editor con la hoja de constancia de firmas.
 */
class RadicacionGrupoDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL_GRUPOS = '/api/mi-bandeja/grupos-colaborativos';

    private User $creador;

    private User $revisor;

    private User $firmante;

    private int $documento;

    private int $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        Queue::fake();
        Storage::fake('radicados_enviados');
        Storage::fake('radicados_internos');

        $permisos = array_map(fn ($p) => "Mi Bandeja - Grupos Colaborativos -> {$p}", ['Ver', 'Crear', 'Gestionar Miembros', 'Enviar Tramite']);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->creador = $this->usuarioConCargo('Creador');
        $this->revisor = $this->usuarioConCargo('Revisor');
        $this->firmante = $this->usuarioConCargo('Firmante');
        $this->creador->givePermissionTo($permisos);
        $this->revisor->givePermissionTo($permisos[0]);
        $this->firmante->givePermissionTo([$permisos[0], $permisos[3]]);

        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->creador->id, 'titulo' => 'Oficio de respuesta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $contenido = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Cordial saludo']]]]];
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $this->documento, 'contenido_yjs' => json_encode($contenido),
            'hash_contenido' => hash('sha256', json_encode($contenido)), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grupo = $this->actingAs($this->creador)->postJson(self::URL_GRUPOS, [
            'nombre' => 'Oficio', 'asunto' => 'Respuesta', 'documento_id' => $this->documento,
        ])->assertCreated()->json('data.id');
        $this->postJson(self::URL_GRUPOS."/{$this->grupo}/revisores", ['user_id' => $this->revisor->id])->assertCreated();
        $this->postJson(self::URL_GRUPOS."/{$this->grupo}/firmantes", ['user_id' => $this->firmante->id, 'orden_firma' => 1])->assertCreated();
    }

    private function usuarioConCargo(string $sufijo): User
    {
        $cargo = CalidadOrganigrama::create(['tipo' => 'Cargo', 'nom_organico' => "Cargo {$sufijo}", 'cod_organico' => "RG{$sufijo}"]);
        $user = User::factory()->create(['estado' => 1, 'nombres' => $sufijo, 'apellidos' => 'Prueba']);
        DB::table('users_cargos')->insert([
            'user_id' => $user->id, 'cargo_id' => $cargo->id, 'fecha_inicio' => now()->toDateString(), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function terminarGrupo(): void
    {
        $this->actingAs($this->revisor)->postJson(self::URL_GRUPOS."/{$this->grupo}/marcar-terminado")->assertOk();
        $this->actingAs($this->firmante)->postJson(self::URL_GRUPOS."/{$this->grupo}/marcar-terminado", [
            'metodo' => 'password', 'password' => 'password',
        ])->assertOk();
    }

    private function grupo(): object
    {
        return DB::table('mi_bandeja_temp')->where('id', $this->grupo)->first();
    }

    private function catalogos(User $user): array
    {
        Schema::disableForeignKeyConstraints();
        $trd = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $user->id, 'tipo' => 'Serie']);
        $detalle = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Grupo documento']);
        $tercero = DB::table('gestion_terceros')->insertGetId(['tipo' => 'Natural', 'nom_razo_soci' => 'Tercero grupo']);
        Schema::enableForeignKeyConstraints();

        return [$trd, $detalle, $tercero];
    }

    public function test_terminado_aparece_en_pendientes_y_ventanilla_lo_radica_con_el_pdf_firmado(): void
    {
        $ventanilla = User::factory()->create(['estado' => 1]);
        $ventanilla->givePermissionTo([
            Permission::findOrCreate('Radicar -> Cores. Enviada -> Listar', 'web'),
            Permission::findOrCreate('Radicar -> Cores. Enviada -> Crear', 'web'),
        ]);
        $pendientes = fn () => array_column($this->actingAs($ventanilla)
            ->getJson('/api/ventanilla/radica-enviada/pendientes-radicar?per_page=100')->assertOk()->json('data'), 'id');

        // Sin terminar no aparece
        $this->assertNotContains($this->grupo, $pendientes());

        $this->terminarGrupo();

        // «Enviar respuesta» es de los grupos con plantilla Word
        $this->actingAs($this->firmante)->postJson(self::URL_GRUPOS."/{$this->grupo}/enviar-tramite")->assertUnprocessable();

        $this->assertContains($this->grupo, $pendientes());
        $this->getJson("/api/ventanilla/radica-enviada/pendientes-radicar/{$this->grupo}")
            ->assertOk()
            ->assertJsonPath('data.documento_editor.id', $this->documento);

        [$trd, $detalle, $tercero] = $this->catalogos($ventanilla);
        $id = $this->postJson('/api/ventanilla/radica-enviada', [
            'clasifica_documen_id' => $trd, 'tercero_id' => $tercero, 'medio_enviado_id' => $detalle,
            'tipo_respuesta_id' => $detalle, 'num_folios' => 1, 'num_anexos' => 0, 'descrip_anexos' => 'N/A',
            'asunto' => 'Respuesta', 'grupo_id' => $this->grupo,
        ])->assertCreated()->json('data.id');

        $radicado = DB::table('ventanilla_radica_enviados')->where('id', $id)->first();
        $this->assertSame('application/pdf', $radicado->archivo_tipo);
        $this->assertSame('Oficio de respuesta.pdf', $radicado->nom_origi);
        $pdf = Storage::disk('radicados_enviados')->get($radicado->archivo_digital);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(hash('sha256', $pdf), $radicado->hash_sha256);
        $this->assertSame('finalizado', $this->grupo()->estado);
        $this->assertSame('radicado', $this->grupo()->respuesta_final);
    }

    public function test_pendientes_no_lista_grupos_del_editor_que_no_se_pueden_radicar(): void
    {
        $ventanilla = User::factory()->create(['estado' => 1]);
        $ventanilla->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Enviada -> Listar', 'web'));
        $pendientes = fn () => array_column($this->actingAs($ventanilla)
            ->getJson('/api/ventanilla/radica-enviada/pendientes-radicar?per_page=100')->assertOk()->json('data'), 'id');

        // Terminado y luego anulado: ya no se puede radicar
        $this->terminarGrupo();
        $this->assertContains($this->grupo, $pendientes());
        $this->creador->givePermissionTo(Permission::findOrCreate('Mi Bandeja - Grupos Colaborativos -> Eliminar', 'web'));
        $this->actingAs($this->creador)->postJson(self::URL_GRUPOS."/{$this->grupo}/anular")->assertOk();
        $this->assertNotContains($this->grupo, $pendientes());

        // Sin firmantes, aunque todos sus miembros cumplieron
        $documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->creador->id, 'titulo' => 'Sin firmantes', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sinFirmantes = $this->actingAs($this->creador)->postJson(self::URL_GRUPOS, [
            'nombre' => 'Sin firmantes', 'asunto' => 'Respuesta', 'documento_id' => $documento,
        ])->assertCreated()->json('data.id');
        $this->postJson(self::URL_GRUPOS."/{$sinFirmantes}/revisores", ['user_id' => $this->revisor->id])->assertCreated();
        $this->actingAs($this->revisor)->postJson(self::URL_GRUPOS."/{$sinFirmantes}/marcar-terminado")->assertOk();
        $this->assertNotContains($sinFirmantes, $pendientes());
    }

    public function test_un_documento_sin_terminar_no_se_radica_desde_ventanilla(): void
    {
        $ventanilla = User::factory()->create(['estado' => 1]);
        $ventanilla->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Enviada -> Crear', 'web'));
        [$trd, $detalle, $tercero] = $this->catalogos($ventanilla);

        $this->actingAs($ventanilla)->postJson('/api/ventanilla/radica-enviada', [
            'clasifica_documen_id' => $trd, 'tercero_id' => $tercero, 'medio_enviado_id' => $detalle,
            'tipo_respuesta_id' => $detalle, 'num_folios' => 1, 'num_anexos' => 0, 'descrip_anexos' => 'N/A',
            'asunto' => 'Respuesta', 'grupo_id' => $this->grupo,
        ])->assertUnprocessable()->assertJsonValidationErrors('grupo_id');
    }

    public function test_el_firmante_lo_radica_como_interno_con_el_pdf_firmado(): void
    {
        if (! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            $this->markTestSkipped('BD de pruebas sin la tabla ventanilla_radica_internos_responsa.');
        }

        $this->firmante->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Interna -> Crear', 'web'));
        [$trd] = $this->catalogos($this->firmante);
        $datos = [
            'asunto' => 'Comunicación interna', 'clasifica_documen_id' => $trd, 'num_folios' => 1,
            'destinatarios' => [$this->creador->id],
            'responsables' => [['user_id' => $this->firmante->id, 'custodio' => true]],
            'grupo_id' => $this->grupo,
        ];

        // Sin terminar el grupo no se radica
        $this->actingAs($this->firmante)->postJson('/api/ventanilla/radica-interno', $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('grupo_id');

        $this->terminarGrupo();
        $id = $this->actingAs($this->firmante)->postJson('/api/ventanilla/radica-interno', $datos)->assertCreated()->json('data.id');

        $radicado = DB::table('ventanilla_radica_internos')->where('id', $id)->first();
        $this->assertSame('application/pdf', $radicado->archivo_tipo);
        $this->assertStringStartsWith('%PDF', Storage::disk('radicados_internos')->get($radicado->archivo_digital));
        $this->assertSame('finalizado', $this->grupo()->estado);
        $this->assertSame('inactivo', $this->grupo()->estado_grupo);
    }
}
