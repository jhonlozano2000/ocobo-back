<?php

namespace Tests\Feature\Gestion;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B24 (diseño de expedientes §2.1 y §6.4): "Todo lo del tercero" reúne radicados, PQRS,
 * expedientes e instancias, pero cada sección respeta el acceso del usuario y la identidad
 * reservada de las PQRS anónimas o de denuncia.
 */
class ConsultaIntegralTerceroTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otro;

    private int $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ofi_archivo_expediente_terceros') || ! Schema::hasColumn('workflow_instancias', 'radicado_id')) {
            $this->markTestSkipped('Requiere la migración 2026_10_19_170000 y el origen de instancias (M19-30).');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Gestión - Terceros -> Mostrar', 'web'));
        $this->tercero = DB::table('gestion_terceros')->insertGetId([
            'num_docu_nit' => 'TST-B24I-'.uniqid(), 'nom_razo_soci' => 'Tercero Integral', 'tipo' => 'Natural',
        ]);
    }

    private function recibido(int $usuario, string $asunto): int
    {
        return DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'B24R-'.uniqid(), 'asunto' => $asunto, 'medio_recep_id' => 1, 'clasifica_documen_id' => 1,
            'usuario_crea' => $usuario, 'tercero_id' => $this->tercero, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function expediente(User $apertura, string $nombre): int
    {
        return DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'B24E-'.uniqid(), 'nombre_expediente' => $nombre, 'anio' => 2026,
            'dependencia_id' => 999999, 'serie_trd_id' => 1, 'estado' => 'abierto', 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $apertura->id,
        ]);
    }

    private function consulta()
    {
        return $this->actingAs($this->user)->getJson("/api/gestion/terceros/{$this->tercero}/expediente-integral");
    }

    public function test_cada_seccion_respeta_el_acceso_del_usuario(): void
    {
        Schema::disableForeignKeyConstraints();
        $propio = $this->recibido($this->user->id, 'Solicitud propia');
        $ajeno = $this->recibido($this->otro->id, 'Solicitud ajena');
        DB::table('ventanilla_radica_enviados')->insert([
            'num_radicado' => 'B24S-'.uniqid(), 'asunto' => 'Respuesta ajena', 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tipo_respuesta_id' => 1, 'usuario_crea' => $this->otro->id, 'tercero_id' => $this->tercero,
        ]);
        DB::table('ventanilla_pqrs')->insert([
            'ventanilla_radica_reci_id' => $propio, 'tipo_pqrs_id' => 999999, 'fecha_vencimiento' => '2026-11-10',
            'estado_tramite' => 'Pendiente', 'es_anonimo' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Vinculado y propio; con su radicado incorporado (sin vínculo) pero ajeno; vinculado y ajeno
        $vinculado = $this->expediente($this->user, 'Contrato propio');
        $porRadicado = $this->expediente($this->user, 'Expediente del radicado');
        $fuera = $this->expediente($this->otro, 'Expediente ajeno');
        DB::table('ofi_archivo_expediente_terceros')->insert([
            ['expediente_id' => $vinculado, 'tercero_id' => $this->tercero, 'rol' => 'titular'],
            ['expediente_id' => $fuera, 'tercero_id' => $this->tercero, 'rol' => 'titular'],
        ]);
        DB::table('ofi_archivo_expedientes_documentos')->insert([
            'expediente_id' => $porRadicado, 'documentable_type' => VentanillaRadicaReci::class,
            'documentable_id' => $ajeno, 'asunto' => 'Solicitud ajena', 'fecha_documento' => '2026-10-19', 'usuario_id' => $this->user->id,
            'activo' => true, 'orden' => 0, 'numero_folio' => 1, 'tipo_documental' => 'Oficio',
        ]);

        foreach ([$propio, $ajeno] as $radicado) {
            DB::table('workflow_instancias')->insert([
                'workflow_id' => 999999, 'estado' => 'en_curso', 'usuario_ejecuta_id' => $this->user->id,
                'origen_tipo' => 'radicado_recibido', 'origen_id' => $radicado, 'radicado_id' => $radicado, 'fecha_inicio' => now(),
            ]);
        }
        Schema::enableForeignKeyConstraints();

        $datos = $this->consulta()->assertOk()->json('data');

        $this->assertSame(['Solicitud propia'], array_column($datos['radicados_recibidos'], 'asunto'));
        $this->assertSame([], $datos['radicados_enviados']);
        $this->assertCount(1, $datos['pqrs']);
        $this->assertEqualsCanonicalizing(['Contrato propio', 'Expediente del radicado'], array_column($datos['expedientes'], 'nombre_expediente'));
        $roles = array_column($datos['expedientes'], 'roles', 'nombre_expediente');
        $this->assertSame(['titular'], $roles['Contrato propio']);
        $this->assertSame([$propio], array_column($datos['instancias'], 'radicado_id'));
        $this->assertSame(['radicados_recibidos' => 1, 'radicados_enviados' => 0, 'pqrs' => 1, 'expedientes' => 2, 'instancias' => 1], $datos['totales']);

        // La vista 360 por identificación ya no lista lo ajeno
        $num = DB::table('gestion_terceros')->where('id', $this->tercero)->value('num_docu_nit');
        $this->getJson("/api/archivo/terceros/{$num}/historial")->assertOk()
            ->assertJsonCount(1, 'data.radicados_recibidos')->assertJsonPath('data.total_tramites', 1);
    }

    public function test_la_pqrs_anonima_y_su_radicado_no_revelan_al_tercero(): void
    {
        Schema::disableForeignKeyConstraints();
        $radicado = $this->recibido($this->user->id, 'Denuncia');
        DB::table('ventanilla_pqrs')->insert([
            'ventanilla_radica_reci_id' => $radicado, 'gestion_tercero_id' => $this->tercero, 'tipo_pqrs_id' => 999999,
            'fecha_vencimiento' => '2026-11-10', 'estado_tramite' => 'Pendiente', 'es_anonimo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $expediente = $this->expediente($this->user, 'Expediente de la denuncia');
        DB::table('ofi_archivo_expedientes_documentos')->insert([
            'expediente_id' => $expediente, 'documentable_type' => VentanillaRadicaReci::class,
            'documentable_id' => $radicado, 'asunto' => 'Denuncia', 'fecha_documento' => '2026-10-19', 'usuario_id' => $this->user->id,
            'activo' => true, 'orden' => 0, 'numero_folio' => 1, 'tipo_documental' => 'Oficio',
        ]);
        Schema::enableForeignKeyConstraints();

        $this->consulta()->assertOk()->assertJsonPath('data.totales', [
            'radicados_recibidos' => 0, 'radicados_enviados' => 0, 'pqrs' => 0, 'expedientes' => 0, 'instancias' => 0,
        ]);

        $this->user->givePermissionTo(Permission::findOrCreate(VentanillaPqrs::PERMISO_IDENTIDAD_RESERVADA, 'web'));
        $this->consulta()->assertOk()
            ->assertJsonPath('data.totales.radicados_recibidos', 1)
            ->assertJsonPath('data.totales.pqrs', 1)
            ->assertJsonPath('data.totales.expedientes', 1);
    }

    public function test_exige_el_permiso_de_consultar_terceros(): void
    {
        $this->actingAs($this->otro)->getJson("/api/gestion/terceros/{$this->tercero}/expediente-integral")->assertForbidden();
    }
}
