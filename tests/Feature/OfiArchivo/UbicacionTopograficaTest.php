<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Configuracion\ConfigVarias;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use App\Services\OfiArchivo\HojaControlService;
use App\Services\OfiArchivo\TransferenciaLotesService;
use App\Services\OfiArchivo\UbicacionTopograficaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B20 (diseño de expedientes §5): la ubicación del expediente físico era texto libre; no había
 * catálogo topográfico, ni control de carpetas ocupadas, ni de cajas llenas, ni reubicación al
 * pasar al archivo central [Acuerdo AGN 001/2024; Acuerdo 004/2019, FUID].
 */
class UbicacionTopograficaTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/archivo/ubicaciones';

    private User $user;

    private int $depId;

    private int $serie;

    protected function setUp(): void
    {
        parent::setUp();

        if (! UbicacionTopograficaService::disponible() || ! HojaControlService::disponible()) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_19_200000 y 2026_10_19_220000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Expedientes -> Ver Todos', 'Expedientes -> Ver', 'Expedientes -> Crear', 'Expedientes -> Editar', 'Expedientes -> Incorporar', 'Ubicaciones -> Administrar'] as $permiso) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> '.$permiso, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B20', 'cod_organico' => 'B20D']);
        $this->serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'S20', 'nom' => 'Contratos', 'a_g' => 2, 'a_c' => 3, 'ct' => 1, 'e' => 0, 'm_d' => 0, 's' => 0,
            'dependencia_id' => $this->depId, 'user_register' => $this->user->id,
        ]);
        Schema::enableForeignKeyConstraints();

        ConfigVarias::setValor(ConfiguracionArchivo::UBICACION_CON_CATALOGO, 'false');
        $this->actingAs($this->user);
    }

    private function expediente(array $datos = []): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create($datos + [
            'numero_expediente' => '2026-B20D-S20-'.uniqid(), 'nombre_expediente' => 'Contrato 20', 'anio' => 2026,
            'dependencia_id' => $this->depId, 'serie_trd_id' => $this->serie, 'estado' => 'abierto',
            'soporte' => 'fisico', 'nivel_acceso' => 'publico', 'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function nodo(array $datos): int
    {
        return $this->postJson(self::API, $datos)->assertCreated()->json('data.id');
    }

    /** Depósito → estante → entrepaño → caja del archivo indicado; devuelve la caja. */
    private function caja(string $archivo = 'gestion', ?int $capacidad = null, string $deposito = 'D1'): int
    {
        $raiz = $this->nodo(['archivo' => $archivo, 'codigo' => $deposito.uniqid()]);
        $estante = $this->nodo(['parent_id' => $raiz, 'codigo' => 'E1']);
        $entrepano = $this->nodo(['parent_id' => $estante, 'codigo' => '2']);

        return $this->nodo(['parent_id' => $entrepano, 'codigo' => '15', 'capacidad' => $capacidad]);
    }

    private function editar(OfiArchivoExpediente $expediente, array $datos)
    {
        return $this->putJson("/api/archivo/expedientes/{$expediente->id}", $datos);
    }

    public function test_el_catalogo_es_un_arbol_de_cuatro_niveles_con_el_archivo_del_deposito(): void
    {
        $raiz = $this->nodo(['archivo' => 'central', 'codigo' => 'DC', 'nombre' => 'Sótano']);
        $estante = $this->postJson(self::API, ['parent_id' => $raiz, 'codigo' => 'E3', 'archivo' => 'gestion'])->assertCreated()->json('data');
        $this->assertSame(['estante', 'central', 'Depósito DC / Estante E3'], [$estante['tipo'], $estante['archivo'], $estante['ruta']]);
        $entrepano = $this->nodo(['parent_id' => $estante['id'], 'codigo' => '1']);
        $caja = $this->nodo(['parent_id' => $entrepano, 'codigo' => '40', 'capacidad' => 8]);

        $this->postJson(self::API, ['parent_id' => $caja, 'codigo' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->postJson(self::API, ['codigo' => 'DZ'])->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->postJson(self::API, ['parent_id' => $raiz, 'codigo' => 'E3'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->postJson(self::API, ['archivo' => 'central', 'codigo' => 'DC'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->postJson(self::API, ['parent_id' => $raiz, 'codigo' => 'E4', 'capacidad' => 5])->assertUnprocessable()->assertJsonValidationErrors('capacidad');

        $catalogo = collect($this->getJson(self::API.'?archivo=central')->assertOk()->json('data.ubicaciones'));
        $this->assertSame(['deposito', 'estante', 'entrepano', 'caja'], $catalogo->pluck('tipo')->all());
        $this->assertSame([8, 0], [$catalogo->last()['capacidad'], $catalogo->last()['ocupadas']]);

        // Sin el permiso de administrar, solo se consulta
        $lector = User::factory()->create(['estado' => 1]);
        $lector->givePermissionTo('Gestion de Archivo -> Expedientes -> Ver');
        $this->actingAs($lector)->getJson(self::API)->assertOk();
        $this->actingAs($lector)->postJson(self::API, ['archivo' => 'central', 'codigo' => 'DY'])->assertForbidden();
    }

    public function test_la_caja_deriva_los_textos_y_con_el_interruptor_apagado_sigue_el_texto_libre(): void
    {
        $caja = $this->caja();
        $expediente = $this->expediente();

        $this->editar($expediente, ['ubicacion_id' => $caja, 'carpeta' => '3', 'ubicacion_fisica' => 'otra cosa'])->assertOk();
        $expediente->refresh();
        $this->assertSame($caja, $expediente->ubicacion_id);
        $this->assertStringEndsWith('/ Estante E1 / Entrepaño 2', $expediente->ubicacion_fisica);
        $this->assertSame(['15', '3'], [$expediente->caja, $expediente->carpeta]);

        // A mano, la ubicación deja de corresponder a la caja del catálogo
        $this->editar($expediente, ['ubicacion_fisica' => 'Bodega norte', 'caja' => '9'])->assertOk();
        $this->assertSame([null, 'Bodega norte', '9', '3'], array_values($expediente->fresh()->only(['ubicacion_id', 'ubicacion_fisica', 'caja', 'carpeta'])));
    }

    public function test_con_el_catalogo_activo_la_ubicacion_no_se_escribe_a_mano(): void
    {
        ConfigVarias::setValor(ConfiguracionArchivo::UBICACION_CON_CATALOGO, 'true');
        $caja = $this->caja();
        $expediente = $this->expediente();

        $this->editar($expediente, ['ubicacion_fisica' => 'Bodega norte', 'caja' => '9'])->assertUnprocessable()->assertJsonValidationErrors('ubicacion_id');
        $this->editar($expediente, ['ubicacion_id' => $caja, 'carpeta' => '1', 'caja' => '9'])->assertOk();
        $this->assertSame('15', $expediente->fresh()->caja);

        // Reenviar los mismos textos con la caja no es escribirlos a mano; quitar la caja los borra
        $actual = $expediente->fresh()->only(['ubicacion_fisica', 'caja']);
        $this->editar($expediente, $actual + ['nombre_expediente' => 'Contrato 20 bis'])->assertOk();
        $this->editar($expediente, ['ubicacion_id' => null])->assertOk();
        $this->assertSame([null, null, null], array_values($expediente->fresh()->only(['ubicacion_id', 'ubicacion_fisica', 'caja'])));
    }

    public function test_carpeta_ocupada_caja_llena_inactiva_o_de_otro_archivo(): void
    {
        $caja = $this->caja('gestion', 2);
        $uno = $this->expediente();
        $dos = $this->expediente();

        $this->editar($uno, ['ubicacion_id' => $caja])->assertUnprocessable()->assertJsonValidationErrors('carpeta');
        $this->editar($uno, ['ubicacion_id' => $caja, 'carpeta' => '1'])->assertOk();
        $this->editar($dos, ['ubicacion_id' => $caja, 'carpeta' => '1'])->assertUnprocessable()
            ->assertJsonPath('errors.carpeta.0', fn ($m) => str_contains($m, $uno->numero_expediente));
        $this->editar($dos, ['ubicacion_id' => $caja, 'carpeta' => '2'])->assertOk();
        // El mismo expediente puede cambiar de carpeta sin contarse dos veces
        $this->editar($uno, ['carpeta' => '1A'])->assertOk();
        $this->editar($this->expediente(), ['ubicacion_id' => $caja, 'carpeta' => '3'])->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion_id.0', 'La caja 15 está llena (2 de 2 carpetas).');
        $this->putJson(self::API."/{$caja}", ['capacidad' => 1])->assertUnprocessable()->assertJsonValidationErrors('capacidad');

        // Desactivar un ancestro inhabilita la caja para ubicaciones nuevas
        $deposito = (int) DB::table('ofi_archivo_ubicaciones')->where('id', DB::table('ofi_archivo_ubicaciones')->where('id', DB::table('ofi_archivo_ubicaciones')->where('id', $caja)->value('parent_id'))->value('parent_id'))->value('parent_id');
        $this->putJson(self::API."/{$caja}", ['capacidad' => 5])->assertOk();
        $this->putJson(self::API."/{$deposito}", ['activo' => false])->assertOk();
        $this->editar($this->expediente(), ['ubicacion_id' => $caja, 'carpeta' => '4'])->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion_id.0', fn ($m) => str_contains($m, 'inactiva'));
        $this->assertFalse(collect($this->getJson(self::API.'?tipo=caja&vigente=1')->json('data.ubicaciones'))->contains('id', $caja));

        $this->editar($this->expediente(), ['ubicacion_id' => $this->caja('central'), 'carpeta' => '1'])->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion_id.0', 'La caja es del archivo central y el expediente está en el archivo de gestión.');
        $this->editar($this->expediente(), ['ubicacion_id' => $deposito, 'carpeta' => '1'])->assertUnprocessable()->assertJsonValidationErrors('ubicacion_id');
    }

    public function test_los_tomos_heredan_la_caja_y_se_ubican_con_el_catalogo(): void
    {
        ConfigVarias::setValor(ConfiguracionArchivo::FOLIOS_POR_TOMO, 5);
        $caja = $this->caja();
        $otra = $this->caja('gestion', null, 'D2');
        $expediente = $this->expediente();
        $ajeno = $this->expediente();
        $this->editar($expediente, ['ubicacion_id' => $caja, 'carpeta' => '1'])->assertOk();
        $this->editar($ajeno, ['ubicacion_id' => $otra, 'carpeta' => '7'])->assertOk();

        $tipo = DB::table('clasificacion_documental_trd')->insertGetId(['tipo' => 'TipoDocumento', 'cod' => 'T1', 'nom' => 'Contrato', 'parent' => $this->serie, 'dependencia_id' => $this->depId, 'user_register' => $this->user->id]);
        $url = "/api/archivo/expedientes/{$expediente->id}";
        $this->postJson("{$url}/hoja-control", ['tipo_documental_id' => $tipo, 'folios' => 4])->assertCreated();
        $tomos = $this->postJson("{$url}/hoja-control", ['tipo_documental_id' => $tipo, 'folios' => 4])->assertCreated()->json('data.tomos');
        $this->assertSame([[$caja, '15', '1'], [$caja, '15', '1']], array_map(fn ($t) => [$t['ubicacion_id'], $t['caja'], $t['carpeta']], $tomos));

        $this->putJson("{$url}/tomos/{$tomos[1]['id']}", ['ubicacion_id' => $otra, 'carpeta' => '7'])->assertUnprocessable()->assertJsonValidationErrors('carpeta');
        $tomo = $this->putJson("{$url}/tomos/{$tomos[1]['id']}", ['ubicacion_id' => $otra, 'carpeta' => '8'])->assertOk()->json('data.tomos.1');
        $this->assertSame([$otra, '15', '8'], [$tomo['ubicacion_id'], $tomo['caja'], $tomo['carpeta']]);

        // Reubicar no altera la huella de la hoja de control firmada
        $hoja = app(HojaControlService::class);
        $antes = $hoja->hash($expediente);
        $this->putJson("{$url}/tomos/{$tomos[1]['id']}", ['ubicacion_id' => $caja, 'carpeta' => '2'])->assertOk();
        $this->assertSame($antes, $hoja->hash($expediente));
    }

    public function test_recibido_en_el_central_queda_por_ubicar_y_la_transferencia_exige_su_caja(): void
    {
        $gestion = $this->caja('gestion');
        $central = $this->caja('central');
        $expediente = $this->expediente(['ubicacion_id' => $gestion, 'ubicacion_fisica' => 'x', 'caja' => '15', 'carpeta' => '1']);
        $sinCaja = $this->expediente(['ubicacion_fisica' => 'Bodega', 'caja' => '1', 'carpeta' => '1']);
        $electronico = $this->expediente(['soporte' => 'electronico']);
        DB::table('ofi_archivo_expedientes')->where('id', $expediente->id)->update(['estado' => 'cerrado', 'fase' => 'central']);

        $pendientes = collect($this->getJson(self::API.'/pendientes?search=B20D')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($pendientes->contains($expediente->id));
        $this->assertTrue($pendientes->contains($sinCaja->id));
        $this->assertFalse($pendientes->contains($electronico->id));
        $this->assertSame([$expediente->id], collect($this->getJson(self::API.'/pendientes?search=B20D&fase=central')->json('data'))->pluck('id')->all());

        // Con el catálogo activo, la transferencia secundaria exige la caja del central
        ConfigVarias::setValor(ConfiguracionArchivo::UBICACION_CON_CATALOGO, 'true');
        $lotes = app(TransferenciaLotesService::class);
        $this->assertSame('Está en una caja fuera del archivo central; reubíquelo primero', $lotes->validarExpediente($expediente->fresh(), 'secundaria'));
        DB::table('ofi_archivo_expedientes')->where('id', $sinCaja->id)->update(['estado' => 'cerrado', 'fase' => 'central']);
        $this->assertSame('Le falta la caja del catálogo de ubicaciones', $lotes->validarExpediente($sinCaja->fresh(), 'secundaria'));

        // Cerrado y en el central, se reubica con su propia ruta
        $this->putJson("/api/archivo/expedientes/{$expediente->id}/ubicacion", ['ubicacion_id' => $this->caja('gestion', null, 'D3'), 'carpeta' => '2'])->assertUnprocessable();
        $this->putJson("/api/archivo/expedientes/{$expediente->id}/ubicacion", ['ubicacion_id' => $central, 'carpeta' => '1'])->assertOk()
            ->assertJsonPath('data.expediente.ubicacion_id', $central);
        $this->assertNotSame('Está en una caja fuera del archivo central; reubíquelo primero', $lotes->validarExpediente($expediente->fresh(), 'secundaria'));
        $this->assertFalse(collect($this->getJson(self::API.'/pendientes?search=B20D')->json('data'))->contains('id', $expediente->id));
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $expediente->id, 'accion' => 'edicion']);
        $this->putJson("/api/archivo/expedientes/{$electronico->id}/ubicacion", ['ubicacion_id' => $gestion, 'carpeta' => '5'])->assertStatus(422);
    }

    public function test_renombrar_actualiza_las_rutas_y_solo_se_elimina_lo_que_no_se_usa(): void
    {
        $caja = $this->caja();
        $entrepano = (int) DB::table('ofi_archivo_ubicaciones')->where('id', $caja)->value('parent_id');
        $estante = (int) DB::table('ofi_archivo_ubicaciones')->where('id', $entrepano)->value('parent_id');
        $expediente = $this->expediente();
        $this->editar($expediente, ['ubicacion_id' => $caja, 'carpeta' => '1'])->assertOk();

        $this->putJson(self::API."/{$estante}", ['codigo' => 'E9'])->assertOk();
        $this->assertStringEndsWith('/ Estante E9 / Entrepaño 2', $expediente->fresh()->ubicacion_fisica);
        $this->assertStringEndsWith('/ Estante E9 / Entrepaño 2 / Caja 15', DB::table('ofi_archivo_ubicaciones')->where('id', $caja)->value('ruta'));
        $this->putJson(self::API."/{$caja}", ['codigo' => '16'])->assertOk();
        $this->assertSame('16', $expediente->fresh()->caja);

        $this->deleteJson(self::API."/{$entrepano}")->assertUnprocessable();
        $this->deleteJson(self::API."/{$caja}")->assertUnprocessable();
        $this->editar($expediente, ['ubicacion_id' => null])->assertOk();
        $this->deleteJson(self::API."/{$caja}")->assertOk();
        $this->assertDatabaseMissing('ofi_archivo_ubicaciones', ['id' => $caja]);
    }
}
