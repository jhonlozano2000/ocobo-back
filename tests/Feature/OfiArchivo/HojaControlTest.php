<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Configuracion\ConfigVarias;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteHojaControlVersion;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\User;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use App\Services\OfiArchivo\HojaControlService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B19 (diseño de expedientes §3.3): el expediente físico solo tenía un contador de folios; no había
 * hoja de control por documento, ni tomos, ni una hoja firmada al cierre [Acuerdo AGN 001/2024
 * A.4.3.1.8 num. 5].
 */
class HojaControlTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    private int $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! HojaControlService::disponible()) {
            $this->markTestSkipped('Requiere la migración 2026_10_19_200000.');
        }

        $this->user = User::factory()->create(['estado' => 1, 'nombres' => 'Rosa', 'apellidos' => 'Archivo']);
        foreach (['Ver Todos', 'Ver', 'Editar', 'Incorporar', 'Cerrar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B19', 'cod_organico' => 'B19D']);
        $nodo = fn (array $datos) => DB::table('clasificacion_documental_trd')->insertGetId($datos + [
            'dependencia_id' => $depId, 'user_register' => $this->user->id,
        ]);
        $serie = $nodo(['tipo' => 'Serie', 'cod' => 'S19', 'nom' => 'Contratos', 'a_g' => 2, 'a_c' => 3, 'ct' => 1, 'e' => 0, 'm_d' => 0, 's' => 0]);
        $this->tipo = $nodo(['tipo' => 'TipoDocumento', 'cod' => 'T1', 'nom' => 'Contrato firmado', 'parent' => $serie]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B19D-S19-'.uniqid(), 'nombre_expediente' => 'Contrato 19', 'anio' => 2026,
            'dependencia_id' => $depId, 'serie_trd_id' => $serie, 'estado' => 'abierto',
            'soporte' => 'fisico', 'nivel_acceso' => 'publico', 'ubicacion_fisica' => 'Archivo de gestión, estante 2',
            'caja' => '7', 'carpeta' => '1', 'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function url(string $ruta = ''): string
    {
        return "/api/archivo/expedientes/{$this->expediente->id}/hoja-control{$ruta}";
    }

    private function registrar(int $folios, array $datos = [])
    {
        return $this->postJson($this->url(), $datos + ['tipo_documental_id' => $this->tipo, 'folios' => $folios, 'fecha_documento' => '2026-03-01']);
    }

    private function cerrar()
    {
        return $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", ['motivo_cierre' => 'Terminó el trámite']);
    }

    public function test_la_foliacion_es_continua_y_un_documento_no_se_parte_entre_tomos(): void
    {
        ConfigVarias::setValor(ConfiguracionArchivo::FOLIOS_POR_TOMO, 10);

        $this->registrar(4)->assertCreated()->assertJsonPath('message', 'Documento registrado en los folios 1 a 4');
        $this->registrar(5)->assertCreated();
        // 10-12 no cabe en el tomo 1 (folios 1-10): va completo al tomo 2
        $estado = $this->registrar(3)->assertCreated()->json('data');

        $this->assertSame([[1, 4, 1], [5, 9, 1], [10, 12, 2]], array_map(fn ($r) => [$r['folio_inicial'], $r['folio_final'], $r['tomo']], $estado['registros']));
        $this->assertSame([1, 10], array_column($estado['tomos'], 'folio_inicial'));
        $this->assertSame([9, 3], array_column($estado['tomos'], 'folios'));
        // El tomo nuevo hereda la ubicación del anterior
        $this->assertSame(['7', '1'], [$estado['tomos'][1]['caja'], $estado['tomos'][1]['carpeta']]);
        $this->assertSame(12, $estado['folios_fisicos']);
        $this->assertSame(12, $this->expediente->fresh()->folios_fisicos);

        // Un documento más grande que el tomo entra solo en uno vacío
        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/tomos", ['caja' => '8'])->assertCreated()
            ->assertJsonPath('message', 'Tomo 3 abierto desde el folio 13');
        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/tomos")->assertStatus(422);
        $estado = $this->registrar(15)->assertCreated()->json('data');
        $ultimo = end($estado['registros']);
        $this->assertSame([13, 27, 3], [$ultimo['folio_inicial'], $ultimo['folio_final'], $ultimo['tomo']]);
        $this->assertSame(['8', '1'], [$estado['tomos'][2]['caja'], $estado['tomos'][2]['carpeta']]);

        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $this->expediente->id, 'accion' => 'hoja_control_registro']);
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $this->expediente->id, 'accion' => 'tomo_abierto']);
    }

    public function test_anular_no_renumera_y_valida_el_tipo_documental(): void
    {
        $this->registrar(3);
        $segundo = $this->registrar(4)->json('data.registros.1.id');
        $this->registrar(2);

        $this->postJson($this->url("/{$segundo}/anular"), ['motivo' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $estado = $this->postJson($this->url("/{$segundo}/anular"), ['motivo' => 'Se devolvió al contratista'])->assertOk()->json('data');
        $this->assertSame(5, $estado['folios_fisicos']);
        $this->assertTrue($estado['registros'][1]['anulado']);
        $this->assertSame([1, 4, 8], array_column($estado['registros'], 'folio_inicial'));
        $this->postJson($this->url("/{$segundo}/anular"), ['motivo' => 'Se devolvió al contratista'])->assertUnprocessable();
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $this->expediente->id, 'accion' => 'hoja_control_anulacion']);

        // El siguiente documento sigue después del último folio, anulado o no
        $this->assertSame(10, $this->registrar(1)->json('data.registros.3.folio_inicial'));

        $ajeno = DB::table('clasificacion_documental_trd')->insertGetId(['tipo' => 'TipoDocumento', 'cod' => 'TX', 'nom' => 'Ajeno', 'user_register' => $this->user->id]);
        $this->registrar(1, ['tipo_documental_id' => $ajeno])->assertUnprocessable()->assertJsonValidationErrors('tipo_documental_id');
        $this->registrar(1, ['fecha_documento' => now()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('fecha_documento');
    }

    public function test_con_hoja_los_folios_fisicos_y_el_soporte_no_se_editan_a_mano(): void
    {
        $url = "/api/archivo/expedientes/{$this->expediente->id}";
        $this->putJson($url, ['folios_fisicos' => 40])->assertOk();

        $this->registrar(6);
        $this->putJson($url, ['folios_fisicos' => 40])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'hoja de control'));
        $this->putJson($url, ['folios_fisicos' => 6, 'caja' => '9'])->assertOk();
        $this->putJson($url, ['soporte' => 'electronico'])->assertStatus(422);
        $this->assertSame(['fisico', 6], [$this->expediente->fresh()->soporte, $this->expediente->fresh()->folios_fisicos]);
    }

    public function test_el_cierre_exige_la_hoja_y_la_firma(): void
    {
        $this->cerrar()->assertUnprocessable()->assertJsonPath('errors.cierre.0', 'La hoja de control no tiene documentos físicos registrados.');

        $this->registrar(5);
        $borrador = $this->get($this->url('/pdf'))->assertOk();
        $this->assertStringStartsWith('%PDF', $borrador->getContent());
        $this->assertStringContainsString('_borrador.pdf', $borrador->headers->get('Content-Disposition'));

        $this->cerrar()->assertOk();

        $version = OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $this->expediente->id)->sole();
        $this->assertSame([1, 1, 5, 1], [$version->version, $version->total_documentos, $version->total_folios, $version->total_tomos]);
        $this->assertMatchesRegularExpression('/^HC-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $version->codigo_verificacion);
        $this->assertSame('Rosa Archivo', $version->firmante);
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $this->expediente->id, 'accion' => 'hoja_control_firmada']);

        // Cerrado, el PDF es el firmado
        $firmado = $this->get($this->url('/pdf'))->assertOk();
        $this->assertSame($version->hash_pdf, $firmado->headers->get('X-Hash-Sha256'));
        $this->assertStringContainsString('_v1.pdf', $firmado->headers->get('Content-Disposition'));
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.integridad.valida', true)
            ->assertJsonPath('data.versiones.0.codigo_verificacion', $version->codigo_verificacion);

        // Cerrado no admite documentos, ni por la aplicación ni directo en la base
        $this->registrar(1)->assertUnprocessable();
        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/tomos")->assertUnprocessable();
        $registro = DB::table('ofi_archivo_expediente_hoja_control')->where('expediente_id', $this->expediente->id)->first();
        if (DB::getDriverName() === 'mysql') {
            $this->assertQueryFalla(fn () => DB::table('ofi_archivo_expediente_hoja_control')->where('id', $registro->id)->update(['anulado_en' => now()]));
            $this->assertQueryFalla(fn () => DB::table('ofi_archivo_expediente_hoja_control')->where('id', $registro->id)->delete());
            $this->assertQueryFalla(fn () => DB::table('ofi_archivo_expediente_tomos')->where('id', $registro->tomo_id)->update(['folio_inicial' => 2]));
            $this->assertQueryFalla(fn () => DB::table('ofi_archivo_expediente_hoja_control_versiones')->where('id', $version->id)->update(['motivo' => 'otro']));
        }

        // La ubicación del tomo sí cambia después del cierre, y no invalida la hoja firmada
        $this->putJson("/api/archivo/expedientes/{$this->expediente->id}/tomos/{$registro->tomo_id}", ['caja' => '31', 'carpeta' => '2'])->assertOk();
        $this->assertDatabaseHas('ofi_archivo_expediente_tomos', ['id' => $registro->tomo_id, 'caja' => '31', 'carpeta' => '2']);
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $this->expediente->id, 'accion' => 'tomo_ubicacion']);
        $this->getJson($this->url())->assertJsonPath('data.integridad.valida', true);
    }

    public function test_el_hibrido_cruza_la_hoja_con_el_indice_del_mismo_cierre(): void
    {
        $this->expediente->update(['soporte' => 'hibrido']);
        OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $this->expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Contrato firmado',
            'tipo_documental_id' => $this->tipo, 'asunto' => 'Contrato', 'fecha_documento' => '2026-03-01',
            'hash_sha256' => hash('sha256', 'contrato'), 'usuario_id' => $this->user->id, 'activo' => true, 'orden' => 0,
        ]);
        $this->registrar(12);

        $this->cerrar()->assertOk();

        $indice = OfiArchivoExpedienteIndiceVersion::where('expediente_id', $this->expediente->id)->latest('version')->firstOrFail();
        $hoja = OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $this->expediente->id)->sole();
        $this->assertSame($indice->id, $hoja->indice_version_id);

        $nodo = simplexml_load_string($indice->xml)->HojaControl;
        $this->assertSame(['12', '1', $hoja->hash_contenido], [(string) $nodo['folios'], (string) $nodo['tomos'], (string) $nodo['huella']]);
    }

    public function test_la_verificacion_detecta_la_alteracion(): void
    {
        $this->registrar(5);
        $this->cerrar()->assertOk();
        $servicio = app(HojaControlService::class);
        $expediente = $this->expediente->fresh();
        $version = OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $expediente->id)->sole();

        $this->assertTrue($servicio->verificarVersion($version, $expediente)['valida']);

        $alterada = (clone $version)->forceFill(['pdf' => $version->pdf.' ']);
        $this->assertSame('El PDF no coincide con su huella', $servicio->verificarVersion($alterada, $expediente)['motivo']);

        $alterada = (clone $version)->forceFill(['total_folios' => 6]);
        $this->assertSame('La firma no corresponde a la versión', $servicio->verificarVersion($alterada, $expediente)['motivo']);

        $alterada = (clone $version)->forceFill(['firmante' => 'Otra persona']);
        $this->assertSame('La firma no corresponde a la versión', $servicio->verificarVersion($alterada, $expediente)['motivo']);

        $alterada = (clone $version)->forceFill(['hash_contenido' => str_repeat('0', 64)]);
        $this->assertSame('La firma no corresponde a la versión', $servicio->verificarVersion($alterada, $expediente)['motivo']);

        // Reabierto, la hoja cambia y la versión firmada ya no la describe
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Reabrir', 'web'));
        $this->postJson("/api/archivo/expedientes/{$expediente->id}/reabrir", ['motivo_reapertura' => 'Llegó un otrosí'])->assertOk();
        $this->registrar(2)->assertCreated();
        $this->assertSame('La hoja cambió después de firmarse', $servicio->verificarVersion($version, $expediente->fresh())['motivo']);

        // El nuevo cierre firma la versión 2
        $this->cerrar()->assertOk();
        $this->assertSame([2, 7], [$servicio->ultimaVersion($expediente)->version, $servicio->ultimaVersion($expediente)->total_folios]);
    }

    public function test_el_expediente_electronico_no_lleva_hoja(): void
    {
        $this->expediente->update(['soporte' => 'electronico']);

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.aplica', false)->assertJsonPath('data.tipos_documentales', []);
        $this->get($this->url('/pdf'))->assertNotFound();
        $this->registrar(2)->assertUnprocessable();
    }

    private function assertQueryFalla(callable $consulta): void
    {
        try {
            $consulta();
            $this->fail('La base de datos permitió la modificación.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
        }
    }
}
