<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * B11 (diseño de expedientes): la BD impide alterar un expediente que ya no está abierto, sus
 * folios y su índice, aunque se escriba directo en las tablas sin pasar por la aplicación.
 */
class InmutabilidadExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $depId;

    private int $serie;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' || ! DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', 'ofi_archivo_expedientes_inmutable_update')->exists()) {
            $this->markTestSkipped('Requiere MySQL y la migración 2026_10_19_150000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B11', 'cod_organico' => 'B11D']);
        $this->serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'S11', 'nom' => 'Contratos', 'dependencia_id' => $this->depId, 'user_register' => $this->user->id,
        ]);
        Schema::enableForeignKeyConstraints();
        $this->actingAs($this->user);
    }

    private function expediente(): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B11D-S11-'.uniqid(), 'nombre_expediente' => 'Contrato 11', 'anio' => 2026,
            'dependencia_id' => $this->depId, 'serie_trd_id' => $this->serie, 'estado' => 'abierto', 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-10-19', 'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function folio(OfiArchivoExpediente $expediente): OfiArchivoExpedienteDocumento
    {
        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Acta', 'tipo_documental_id' => 1,
            'asunto' => 'Acta', 'fecha_documento' => '2026-10-19', 'hash_sha256' => hash('sha256', 'acta'),
            'usuario_id' => $this->user->id, 'activo' => true, 'orden' => 0,
        ]);
    }

    private function rechaza(callable $escritura, string $mensaje): void
    {
        try {
            $escritura();
            $this->fail("La BD debía rechazar la escritura: {$mensaje}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }
    }

    public function test_un_expediente_cerrado_conserva_su_identificacion_su_cierre_sus_folios_y_su_indice(): void
    {
        $expediente = $this->expediente();
        $folio = $this->folio($expediente);
        DB::table('ofi_archivo_expedientes')->where('id', $expediente->id)->update([
            'estado' => 'cerrado', 'fecha_cierre' => '2026-10-19', 'hash_indice' => str_repeat('c', 64), 'fecha_fin_gestion' => '2028-10-19',
        ]);

        $exp = DB::table('ofi_archivo_expedientes')->where('id', $expediente->id);
        $this->rechaza(fn () => (clone $exp)->update(['nombre_expediente' => 'Otro']), 'su identificación y su clasificación');
        $this->rechaza(fn () => (clone $exp)->update(['subserie_trd_id' => $this->serie]), 'su identificación y su clasificación');
        $this->rechaza(fn () => (clone $exp)->update(['deleted_at' => now()]), 'su identificación y su clasificación');
        $this->rechaza(fn () => (clone $exp)->update(['hash_indice' => str_repeat('d', 64)]), 'su cierre, su índice y su retención');
        $this->rechaza(fn () => (clone $exp)->update(['fecha_fin_gestion' => '2030-01-01']), 'su cierre, su índice y su retención');
        $this->rechaza(fn () => (clone $exp)->delete(), 'no puede eliminarse');

        // La ubicación, el acceso y el estado siguen cambiando (transferencia, desclasificación)
        (clone $exp)->update(['ubicacion_fisica' => 'Archivo central, caja 4', 'nivel_acceso' => 'clasificado', 'estado' => 'transferido']);
        $this->assertSame('Archivo central, caja 4', (clone $exp)->value('ubicacion_fisica'));

        $docs = DB::table('ofi_archivo_expedientes_documentos');
        $this->rechaza(fn () => $this->folio($expediente), 'no se le pueden incorporar documentos');
        $this->rechaza(fn () => (clone $docs)->where('id', $folio->id)->update(['asunto' => 'Otro']), 'sus documentos no pueden modificarse');
        $this->rechaza(fn () => (clone $docs)->where('id', $folio->id)->update(['activo' => false]), 'sus documentos no pueden modificarse');
        $this->rechaza(fn () => (clone $docs)->where('id', $folio->id)->delete(), 'sus documentos no pueden eliminarse');

        $indice = DB::table('ofi_archivo_expediente_indice')->where('documento_id', $folio->id);
        $this->rechaza(fn () => (clone $indice)->update(['anulado_en' => now()]), 'su índice no puede modificarse');
        $this->rechaza(fn () => (clone $indice)->delete(), 'no pueden eliminarse; se anulan');
    }

    public function test_solo_un_expediente_cerrado_se_reabre_y_al_reabrir_limpia_su_cierre(): void
    {
        $expediente = $this->expediente();
        $this->folio($expediente);
        $exp = DB::table('ofi_archivo_expedientes')->where('id', $expediente->id);
        (clone $exp)->update(['estado' => 'cerrado', 'fecha_cierre' => '2026-10-19', 'fecha_fin_gestion' => '2028-10-19', 'trd_snapshot' => '{"a_g":2}']);

        (clone $exp)->update(['estado' => 'abierto', 'fecha_cierre' => null, 'fecha_fin_gestion' => null, 'trd_snapshot' => null]);
        $this->assertNull((clone $exp)->value('fecha_fin_gestion'));
        $this->assertSame(2, $this->folio($expediente)->numero_folio, 'Reabierto admite folios nuevos');

        // Un expediente transferido no se reabre; ni siquiera reabriéndolo cambia su identificación
        (clone $exp)->update(['estado' => 'transferido']);
        $this->rechaza(fn () => (clone $exp)->update(['estado' => 'abierto']), 'Solo un expediente cerrado puede reabrirse');
        (clone $exp)->update(['estado' => 'cerrado']);
        $this->rechaza(fn () => (clone $exp)->update(['estado' => 'abierto', 'numero_expediente' => 'X']), 'su identificación y su clasificación');
    }

    public function test_las_versiones_firmadas_del_indice_solo_admiten_insercion(): void
    {
        $expediente = $this->expediente();
        $this->folio($expediente);
        $id = DB::table('ofi_archivo_expediente_indice_versiones')->insertGetId([
            'expediente_id' => $expediente->id, 'version' => 1, 'motivo' => 'cierre', 'total_folios' => 1,
            'hash_indice' => str_repeat('a', 64), 'hash_cadena' => str_repeat('b', 64), 'xml' => '<x/>', 'hash_xml' => str_repeat('c', 64),
            'pdf' => '%PDF', 'hash_pdf' => str_repeat('d', 64), 'firma_tipo' => 'interna', 'firmado_por' => $this->user->id,
            'firmante' => 'Ana', 'firmado_en' => now(), 'firma' => str_repeat('e', 64), 'codigo_verificacion' => 'IE-'.uniqid(), 'created_at' => now(),
        ]);

        $version = DB::table('ofi_archivo_expediente_indice_versiones')->where('id', $id);
        $this->rechaza(fn () => (clone $version)->update(['pdf' => '%PDF-alterado']), 'no pueden modificarse');
        $this->rechaza(fn () => (clone $version)->delete(), 'no pueden eliminarse');
    }
}
