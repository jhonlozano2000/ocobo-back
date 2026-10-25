<?php

namespace Tests\Feature\Metricas;

use App\Models\Metricas\ArchivoMetrica;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use App\Models\MiDisco\MiDiscoArchivo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Métricas de archivos: el trait RegistraMetricaArchivo registra cargas y eliminaciones después de
 * la respuesta (sin penalizar la carga), el backfill no duplica y el endpoint agrega por periodo,
 * módulo, formato y dependencia.
 */
class ArchivosMetricasTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/metricas/archivos';

    private User $user;

    private int $dependenciaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Reporte -> Metricas de archivos', 'web'));

        Schema::disableForeignKeyConstraints();
        $this->dependenciaId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Gestión Documental']);
        $oficina = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Oficina', 'nom_organico' => 'Correspondencia', 'parent' => $this->dependenciaId]);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Auxiliar', 'parent' => $oficina]);
        DB::table('users_cargos')->insert([
            'user_id' => $this->user->id, 'cargo_id' => $cargo, 'fecha_inicio' => now()->toDateString(), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function metricas(string $type, int $id): array
    {
        return ArchivoMetrica::query()->where('archivable_type', $type)->where('archivable_id', $id)
            ->orderBy('id')->get(['evento', 'modulo', 'submodulo', 'extension', 'bytes', 'user_id', 'dependencia_id', 'dependencia_nom'])
            ->toArray();
    }

    private function archivoMiDisco(array $datos = []): MiDiscoArchivo
    {
        return MiDiscoArchivo::create($datos + [
            'nombre' => 'acta.pdf', 'nombre_original' => 'acta.pdf', 'user_id' => $this->user->id,
            'ruta_almacenamiento' => 'mi_disco/'.$this->user->id.'/'.uniqid().'.pdf', 'peso' => 2048, 'mime_type' => 'application/pdf',
        ]);
    }

    public function test_la_carga_se_registra_despues_de_la_respuesta_con_la_dependencia_del_usuario(): void
    {
        $this->actingAs($this->user);
        $archivo = $this->archivoMiDisco();

        // Nada durante la petición: se inserta al terminar
        $this->assertSame([], $this->metricas($archivo->getMorphClass(), $archivo->id));
        $this->app->terminate();

        $this->assertSame([[
            'evento' => 'cargado', 'modulo' => 'mi_bandeja', 'submodulo' => 'mi_disco', 'extension' => 'pdf', 'bytes' => 2048,
            'user_id' => $this->user->id, 'dependencia_id' => $this->dependenciaId, 'dependencia_nom' => 'Gestión Documental',
        ]], $this->metricas($archivo->getMorphClass(), $archivo->id));
    }

    public function test_borrar_el_archivo_registra_la_eliminacion_a_nombre_de_quien_borra(): void
    {
        // Archivo previo a las métricas (sin evento de carga)
        $id = DB::table('mi_disco_archivos')->insertGetId([
            'nombre' => 'acta.pdf', 'nombre_original' => 'acta.pdf', 'user_id' => 999999,
            'ruta_almacenamiento' => 'mi_disco/acta.pdf', 'peso' => 4096, 'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($this->user);
        MiDiscoArchivo::findOrFail($id)->delete();
        $this->app->terminate();

        $this->assertSame([[
            'evento' => 'eliminado', 'modulo' => 'mi_bandeja', 'submodulo' => 'mi_disco', 'extension' => 'pdf', 'bytes' => 4096,
            'user_id' => $this->user->id, 'dependencia_id' => $this->dependenciaId, 'dependencia_nom' => 'Gestión Documental',
        ]], $this->metricas((new MiDiscoArchivo)->getMorphClass(), $id));
    }

    public function test_reemplazar_el_archivo_registra_eliminado_y_cargado_y_convierte_kb(): void
    {
        $version = MiBandejaTempArchivoVersion::create([
            'grupo_id' => 999999, 'version' => 1, 'nombre_original' => 'oficio.docx', 'nombre_archivo' => 'v1.docx',
            'ruta_completa' => 'grupos/1/v1.docx', 'peso' => 10, 'extension' => 'DOCX', 'mime_type' => 'application/msword',
            'hash_seguridad' => str_repeat('a', 64), 'user_subio_id' => $this->user->id,
        ]);
        $version->update(['comentario_version' => 'sin cambio de archivo']);
        $version->update(['ruta_completa' => 'grupos/1/v1-firmado.pdf', 'peso' => 20.5, 'extension' => 'pdf']);
        $this->app->terminate();

        $this->assertSame(
            [['cargado', 'docx', 10240], ['eliminado', 'docx', 10240], ['cargado', 'pdf', 20992]],
            array_map(fn ($m) => [$m['evento'], $m['extension'], $m['bytes']], $this->metricas($version->getMorphClass(), $version->id))
        );
    }

    public function test_si_la_carga_se_revierte_no_queda_metrica(): void
    {
        DB::beginTransaction();
        $archivo = $this->archivoMiDisco();
        DB::rollBack();
        $this->app->terminate();

        $this->assertSame([], $this->metricas($archivo->getMorphClass(), $archivo->id));
    }

    public function test_backfill_registra_lo_existente_una_sola_vez(): void
    {
        $id = DB::table('mi_disco_archivos')->insertGetId([
            'nombre' => 'viejo.png', 'nombre_original' => 'viejo.png', 'user_id' => $this->user->id,
            'ruta_almacenamiento' => 'mi_disco/viejo.png', 'peso' => 512, 'mime_type' => 'image/png',
            'created_at' => '2025-03-04 10:00:00', 'updated_at' => '2025-03-04 10:00:00',
        ]);

        $this->artisan('metricas:archivos-backfill', ['--modelo' => 'MiDiscoArchivo'])->assertSuccessful();
        $this->artisan('metricas:archivos-backfill', ['--modelo' => 'MiDiscoArchivo'])->assertSuccessful();

        $filas = ArchivoMetrica::query()->where('archivable_type', (new MiDiscoArchivo)->getMorphClass())->where('archivable_id', $id)->get();
        $this->assertCount(1, $filas);
        $this->assertSame(['png', 512, '2025-03-04 10:00:00', $this->dependenciaId], [
            $filas[0]->extension, $filas[0]->bytes, $filas[0]->ocurrido_en->toDateTimeString(), (int) $filas[0]->dependencia_id,
        ]);
    }

    private function evento(string $evento, string $modulo, string $extension, int $bytes, string $fecha): void
    {
        ArchivoMetrica::query()->insert([
            'evento' => $evento, 'modulo' => $modulo, 'submodulo' => $modulo.'_sub', 'archivable_type' => 'prueba',
            'archivable_id' => random_int(1, PHP_INT_MAX), 'ruta_hash' => sha1(uniqid()), 'extension' => $extension,
            'bytes' => $bytes, 'user_id' => $this->user->id, 'dependencia_id' => $this->dependenciaId,
            'dependencia_nom' => 'Gestión Documental', 'ocurrido_en' => $fecha, 'created_at' => now(),
        ]);
    }

    public function test_el_endpoint_agrega_por_periodo_modulo_formato_y_dependencia(): void
    {
        $this->evento('cargado', 'ventanilla_unica', 'pdf', 1000, '2031-01-01 08:00:00');
        $this->evento('cargado', 'ventanilla_unica', 'pdf', 3000, '2031-01-03 09:00:00');
        $this->evento('cargado', 'mi_bandeja', 'docx', 500, '2031-01-03 10:00:00');
        $this->evento('eliminado', 'ventanilla_unica', 'pdf', 1000, '2031-01-03 11:00:00');
        $this->evento('cargado', 'gestion_archivo', 'png', 700, '2030-12-15 12:00:00'); // antes del rango

        $data = $this->actingAs($this->user)->getJson(self::URL.'?'.http_build_query([
            'desde' => '2031-01-01', 'hasta' => '2031-01-03', 'dependencia_id' => $this->dependenciaId,
        ]))->assertOk()->json('data');

        $this->assertSame(
            ['archivos' => 3, 'bytes' => 4500, 'archivos_eliminados' => 1, 'bytes_eliminados' => 1000, 'bytes_netos' => 3500, 'peso_promedio' => 1500],
            $data['totales']
        );
        $this->assertSame(['archivos' => 3, 'bytes' => 4200], $data['almacenamiento']);
        $this->assertSame(['2031-01-01', '2031-01-02', '2031-01-03'], array_column($data['serie'], 'periodo'));
        $this->assertSame([1, 0, 2], array_column($data['serie'], 'archivos'));
        $this->assertSame([0, 0, 1], array_column($data['serie'], 'archivos_eliminados'));
        $this->assertSame([['extension' => 'pdf', 'archivos' => 2, 'bytes' => 4000], ['extension' => 'docx', 'archivos' => 1, 'bytes' => 500]], $data['por_formato']);
        $this->assertSame(['ventanilla_unica', 'mi_bandeja'], array_column($data['por_modulo'], 'modulo'));
        $this->assertSame([['dependencia_id' => $this->dependenciaId, 'dependencia' => 'Gestión Documental', 'archivos' => 3, 'bytes' => 4500]], $data['por_dependencia']);

        $porMes = $this->getJson(self::URL.'?'.http_build_query([
            'desde' => '2030-12-01', 'hasta' => '2031-01-31', 'agrupacion' => 'mes', 'modulo' => 'gestion_archivo', 'dependencia_id' => $this->dependenciaId,
        ]))->assertOk()->json('data');
        $this->assertSame([['2030-12', 1], ['2031-01', 0]], array_map(fn ($p) => [$p['periodo'], $p['archivos']], $porMes['serie']));
    }

    public function test_requiere_permiso_y_valida_el_rango(): void
    {
        $sinPermiso = User::factory()->create(['estado' => 1]);
        $this->actingAs($sinPermiso)->getJson(self::URL)->assertForbidden();

        $this->actingAs($this->user)->getJson(self::URL)->assertOk()->assertJsonCount(30, 'data.serie');
        $this->getJson(self::URL.'?desde=2024-01-01&hasta=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('agrupacion');
        $this->getJson(self::URL.'?desde=2026-02-01&hasta=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson(self::URL.'?modulo=otro')->assertUnprocessable()->assertJsonValidationErrors('modulo');
    }
}
