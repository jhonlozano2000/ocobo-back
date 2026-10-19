<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B09 (diseño de expedientes §6.1, decisión D1): el panel "Expediente" del radicado y de la PQRS
 * sugiere los expedientes abiertos de su clasificación que el usuario puede ver, primero los del
 * mismo tercero, sin revelar el tercero de una PQRS con identidad reservada.
 */
class SugerenciaExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/archivo/expedientes/sugerencias';

    private User $user;

    private User $otro;

    private int $depA;

    private int $depB;

    private int $serie;

    private int $subserie;

    private int $tipoDocumental;

    private int $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ofi_archivo_expediente_terceros') || ! Schema::hasColumn('ventanilla_pqrs', 'es_anonimo')) {
            $this->markTestSkipped('Requiere los terceros del expediente (B24) y la reserva de identidad de la PQRS (M04-03).');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver', 'web'));

        Schema::disableForeignKeyConstraints();
        $this->depA = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep A', 'cod_organico' => 'B09A']);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Cargo A', 'cod_organico' => 'B09AC', 'parent' => $this->depA]);
        $this->depB = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B', 'cod_organico' => 'B09B']);
        DB::table('users_cargos')->insert(['user_id' => $this->user->id, 'cargo_id' => $cargo, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);

        $this->serie = $this->trd('Serie', 'S09', null, $this->depA);
        $this->subserie = $this->trd('SubSerie', 'S09.1', $this->serie, $this->depA);
        $this->tipoDocumental = $this->trd('TipoDocumento', 'Oficio', $this->subserie, $this->depA);
        $this->tercero = DB::table('gestion_terceros')->insertGetId(['num_docu_nit' => 'B09-1', 'nom_razo_soci' => 'Tercero B09']);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function trd(string $tipo, string $cod, ?int $parent, int $dependencia): int
    {
        return DB::table('clasificacion_documental_trd')->insertGetId([
            'user_register' => $this->user->id, 'tipo' => $tipo, 'cod' => $cod, 'nom' => $cod, 'parent' => $parent, 'dependencia_id' => $dependencia,
        ]);
    }

    private function radicado(User $usuario, int $clasificacion, ?int $tercero = null): int
    {
        return DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'B09-'.uniqid(), 'asunto' => 'Solicitud de licencia', 'medio_recep_id' => 1, 'clasifica_documen_id' => $clasificacion,
            'tercero_id' => $tercero, 'usuario_crea' => $usuario->id, 'estado_trabajo' => 'RECIBIDO', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function expediente(int $dependencia, ?int $subserie, string $estado = 'abierto'): int
    {
        return DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'B09-'.uniqid(), 'nombre_expediente' => 'Expediente B09', 'anio' => 2026, 'dependencia_id' => $dependencia,
            'serie_trd_id' => $this->serie, 'subserie_trd_id' => $subserie, 'estado' => $estado, 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-10-01', 'usuario_apertura_id' => $this->otro->id,
        ]);
    }

    private function incorporar(int $expediente, int $radicado, string $clase = VentanillaRadicaReci::class): void
    {
        $folio = DB::table('ofi_archivo_expedientes_documentos')->where('expediente_id', $expediente)->count() + 1;
        DB::table('ofi_archivo_expedientes_documentos')->insert([
            'expediente_id' => $expediente, 'documentable_type' => $clase, 'documentable_id' => $radicado,
            'numero_folio' => $folio, 'orden' => $folio, 'tipo_documental' => 'Oficio', 'fecha_documento' => '2026-10-01', 'asunto' => 'Folio',
            'usuario_id' => $this->user->id, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sugerencia(string $tipo, int $id): array
    {
        return $this->actingAs($this->user)->getJson(self::URL."?origen_tipo={$tipo}&origen_id={$id}")->assertOk()->json('data');
    }

    public function test_sugiere_los_abiertos_de_su_clasificacion_primero_los_del_tercero(): void
    {
        $radicado = $this->radicado($this->user, $this->tipoDocumental, $this->tercero);

        $general = $this->expediente($this->depA, $this->subserie);
        $delTercero = $this->expediente($this->depA, $this->subserie);
        DB::table('ofi_archivo_expediente_terceros')->insert(['expediente_id' => $delTercero, 'tercero_id' => $this->tercero, 'rol' => 'titular', 'usuario_id' => $this->user->id]);
        $this->expediente($this->depA, $this->subserie, 'cerrado');
        $this->expediente($this->depA, null);
        $this->expediente($this->depB, $this->subserie);
        $actual = $this->expediente($this->depA, $this->subserie);
        $this->incorporar($actual, $radicado);
        $this->incorporar($this->expediente($this->depB, $this->subserie), $radicado);

        $data = $this->sugerencia('radicado_recibido', $radicado);

        $this->assertSame([$this->serie, $this->subserie, $this->tipoDocumental], [
            $data['clasificacion']['serie']['id'], $data['clasificacion']['subserie']['id'], $data['clasificacion']['tipo_documental']['id'],
        ]);
        $this->assertSame([$delTercero, $general], array_column($data['sugeridos'], 'id'));
        $this->assertSame([true, false], array_column($data['sugeridos'], 'mismo_tercero'));
        $this->assertSame('radicado_recibido', $data['radicado']['documentable_type']);
        // El que está en otra dependencia se cuenta sin revelarse
        $this->assertSame([$actual], array_column($data['expedientes_actuales']['visibles'], 'id'));
        $this->assertSame(1, $data['expedientes_actuales']['otros']);
        $this->assertSame([
            'permitida' => true, 'dependencia_id' => $this->depA, 'serie_trd_id' => $this->serie,
            'subserie_trd_id' => $this->subserie, 'nombre_expediente' => 'Solicitud de licencia',
        ], $data['apertura']);
    }

    public function test_la_pqrs_con_identidad_reservada_no_usa_el_tercero(): void
    {
        Permission::findOrCreate(VentanillaPqrs::PERMISO_IDENTIDAD_RESERVADA, 'web');
        $tipo = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Denuncia']);
        $radicado = $this->radicado($this->user, $this->serie);
        $pqrs = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicado, 'gestion_tercero_id' => $this->tercero, 'clasificacion_documental_trd_id' => $this->subserie,
            'tipo_pqrs_id' => $tipo, 'fecha_vencimiento' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $delTercero = $this->expediente($this->depA, $this->subserie);
        $this->incorporar($delTercero, $this->radicado($this->user, $this->serie, $this->tercero));

        // La clasificación de la PQRS prevalece sobre la del radicado
        $data = $this->sugerencia('pqrs', $pqrs);
        $this->assertSame($this->subserie, $data['clasificacion']['subserie']['id']);
        $this->assertSame([false], array_column($data['sugeridos'], 'mismo_tercero'));

        $this->user->givePermissionTo(VentanillaPqrs::PERMISO_IDENTIDAD_RESERVADA);
        $this->assertSame([true], array_column($this->sugerencia('pqrs', $pqrs)['sugeridos'], 'mismo_tercero'));
    }

    public function test_respeta_el_acceso_al_radicado_y_a_la_dependencia_de_la_serie(): void
    {
        $this->actingAs($this->user)->getJson(self::URL.'?origen_tipo=radicado_recibido&origen_id='.$this->radicado($this->otro, $this->serie))->assertNotFound();
        $this->getJson(self::URL.'?origen_tipo=radicado_interno&origen_id=1')->assertUnprocessable();

        $sinClasificar = $this->sugerencia('radicado_recibido', $this->radicado($this->user, 0));
        $this->assertSame([], $sinClasificar['sugeridos']);
        $this->assertFalse($sinClasificar['apertura']['permitida']);

        $serieAjena = $this->trd('Serie', 'S09B', null, $this->depB);
        $apertura = $this->sugerencia('radicado_recibido', $this->radicado($this->user, $serieAjena))['apertura'];
        $this->assertFalse($apertura['permitida']);
        $this->assertStringContainsString('otra dependencia', $apertura['motivo']);
    }

    public function test_la_respuesta_se_sugiere_en_el_expediente_de_la_peticion(): void
    {
        $peticion = $this->radicado($this->user, $this->subserie);
        $otraSerie = $this->trd('Serie', 'S10', null, $this->depA);
        $dePeticion = $this->expediente($this->depA, null);
        DB::table('ofi_archivo_expedientes')->where('id', $dePeticion)->update(['serie_trd_id' => $otraSerie]);
        $this->incorporar($dePeticion, $peticion);
        $deSerie = $this->expediente($this->depA, $this->subserie);

        $enviado = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'B09-E-'.uniqid(), 'asunto' => 'Respuesta', 'clasifica_documen_id' => $this->subserie, 'medio_enviado_id' => 1,
            'tercero_id' => $this->tercero, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        DB::table('ventanilla_radica_enviados_respuestas')->insert(['radica_enviado_id' => $enviado, 'radica_reci_id' => $peticion]);

        $data = $this->sugerencia('radicado_enviado', $enviado);

        // El de la petición va primero aunque sea de otra serie
        $this->assertSame([$dePeticion, $deSerie], array_column($data['sugeridos'], 'id'));
        $this->assertSame([true, false], array_column($data['sugeridos'], 'contiene_peticion'));
        $this->assertSame('radicado_enviado', $data['radicado']['documentable_type']);

        $this->incorporar($dePeticion, $enviado, VentanillaRadicaEnviados::class);
        $data = $this->sugerencia('radicado_enviado', $enviado);
        $this->assertSame([$dePeticion], array_column($data['expedientes_actuales']['visibles'], 'id'));
        $this->assertSame([$deSerie], array_column($data['sugeridos'], 'id'));

        $ajeno = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'B09-E-'.uniqid(), 'clasifica_documen_id' => $this->subserie, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->otro->id,
        ]);
        $this->getJson(self::URL.'?origen_tipo=radicado_enviado&origen_id='.$ajeno)->assertNotFound();
    }
}
