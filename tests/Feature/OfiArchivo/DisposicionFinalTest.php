<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use App\Services\OfiArchivo\DisposicionFinalService;
use App\Services\OfiArchivo\TransferenciaLotesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B22 (diseño de expedientes §3.8 S y M/D): la disposición de la TRD era un texto que nadie
 * aplicaba. Los de selección se transferían o eliminaban enteros y la digitalización no quedaba
 * registrada.
 */
class DisposicionFinalTest extends TestCase
{
    use DatabaseTransactions;

    private const PDF = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    private User $archivo;

    private int $dependencia;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archivo_expedientes');

        $this->archivo = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Archivo']);
        foreach (['Disposicion -> Ver', 'Disposicion -> Seleccionar', 'Disposicion -> Digitalizar', 'Expedientes -> Ver Todos'] as $accion) {
            $this->archivo->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> '.$accion, 'web'));
        }
        $this->dependencia = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Oficina B22', 'cod_organico' => 'B22',
        ]);
    }

    /** Serie con 2 años en gestión y 3 en central y la disposición indicada. */
    private function serie(array $disposicion): int
    {
        return DB::table('clasificacion_documental_trd')->insertGetId($disposicion + [
            'tipo' => 'Serie', 'cod' => 'D'.random_int(1000, 9999), 'nom' => 'Historias laborales', 'user_register' => $this->archivo->id,
            'dependencia_id' => $this->dependencia, 'a_g' => '2', 'a_c' => '3', 'ct' => 0, 'e' => 0, 'm_d' => 0, 's' => 0,
        ]);
    }

    /** Expediente cerrado en el archivo central hace $anios años. */
    private function expediente(int $serie, float $anios = 6, array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ofi_archivo_expedientes')->insertGetId($extra + [
            'numero_expediente' => 'B22-'.uniqid(), 'nombre_expediente' => 'Historia laboral', 'anio' => 2018,
            'dependencia_id' => $this->dependencia, 'dependencia_cod' => 'B22', 'dependencia_nom' => 'Oficina B22',
            'serie_trd_id' => $serie, 'estado' => 'cerrado', 'fase' => 'central', 'fecha_apertura' => now()->subYears(8),
            'usuario_apertura_id' => $this->archivo->id, 'fecha_cierre' => now()->subDays((int) round($anios * 365)),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function servicio(): DisposicionFinalService
    {
        return app(DisposicionFinalService::class);
    }

    private function exp(int $id): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::findOrFail($id);
    }

    /** Pasa a disposición y propone la selección de la serie. */
    private function proponer(int $serie): array
    {
        $this->servicio()->marcarCumplidos();

        return $this->actingAs($this->archivo)->postJson('/api/archivo/disposicion/selecciones', ['trd_id' => $serie])
            ->assertCreated()->json('data');
    }

    public function test_la_trd_exige_el_dato_del_metodo_y_lo_borra_si_no_es_seleccion(): void
    {
        $base = ['tipo' => 'Serie', 'nom' => 'Serie', 'dependencia_id' => $this->dependencia, 'a_g' => 2, 'a_c' => 3, 'user_register' => $this->archivo->id];

        try {
            ClasificacionDocumentalTRD::create($base + ['cod' => 'S1', 's' => true, 'metodo_seleccion' => 'aleatorio']);
            $this->fail('La muestra aleatoria sin porcentaje no debía guardarse');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('porcentaje_seleccion', $e->errors());
        }
        try {
            ClasificacionDocumentalTRD::create($base + ['cod' => 'S2', 's' => true, 'metodo_seleccion' => 'sistematico', 'intervalo_seleccion' => 1]);
            $this->fail('El intervalo debe ser al menos 2');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intervalo_seleccion', $e->errors());
        }

        $sistematica = ClasificacionDocumentalTRD::create($base + ['cod' => 'S3', 's' => true, 'metodo_seleccion' => 'sistematico', 'intervalo_seleccion' => 10, 'porcentaje_seleccion' => 30]);
        $this->assertSame([10, null], [$sistematica->intervalo_seleccion, $sistematica->porcentaje_seleccion]);

        $conservacion = ClasificacionDocumentalTRD::create($base + ['cod' => 'S4', 'ct' => true, 'metodo_seleccion' => 'aleatorio', 'porcentaje_seleccion' => 10]);
        $this->assertNull($conservacion->metodo_seleccion);
        $this->assertNull($conservacion->porcentaje_seleccion);
    }

    public function test_al_cumplir_la_retencion_pasa_a_disposicion_con_su_destino_y_avisa(): void
    {
        $ct = $this->expediente($this->serie(['ct' => 1]));
        $e = $this->expediente($this->serie(['e' => 1]));
        $s = $this->expediente($this->serie(['s' => 1, 'metodo_seleccion' => 'sistematico', 'intervalo_seleccion' => 2]));
        $sinCumplir = $this->expediente($this->serie(['ct' => 1]), 4);
        $enGestion = $this->expediente($this->serie(['ct' => 1]), 6, ['fase' => 'gestion']);

        $this->artisan('archivo:marcar-disposicion')->assertSuccessful();

        $estado = fn (int $id) => (array) DB::table('ofi_archivo_expedientes')->where('id', $id)->first(['estado', 'destino_disposicion']);
        $this->assertSame(['estado' => 'en_disposicion', 'destino_disposicion' => 'conservar'], $estado($ct));
        $this->assertSame(['estado' => 'en_disposicion', 'destino_disposicion' => 'eliminar'], $estado($e));
        $this->assertSame(['estado' => 'en_disposicion', 'destino_disposicion' => null], $estado($s));
        $this->assertSame('cerrado', $estado($sinCumplir)['estado']);
        $this->assertSame('cerrado', $estado($enGestion)['estado']);

        $evento = DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $s)->where('accion', 'inicio_disposicion')->first();
        $this->assertSame('S', json_decode($evento->detalle, true)['disposicion']);
        $aviso = Notificacion::where('user_id', $this->archivo->id)->where('type', DisposicionFinalService::TIPO_NOTIFICACION)->latest('id')->first();
        $this->assertStringContainsString('pendientes de selección', $aviso->message);

        // Una segunda corrida no los vuelve a marcar
        $this->assertSame(0, $this->servicio()->marcarCumplidos()->whereIn('id', [$ct, $e, $s])->count());
    }

    public function test_la_seleccion_sistematica_propone_uno_de_cada_n_y_al_confirmar_fija_el_destino(): void
    {
        $serie = $this->serie(['s' => 1, 'metodo_seleccion' => 'sistematico', 'intervalo_seleccion' => 2]);
        $ids = array_map(fn ($n) => $this->expediente($serie, 6 + $n / 10), range(5, 1));

        $this->servicio()->marcarCumplidos();
        $pendientes = $this->actingAs($this->archivo)->getJson('/api/archivo/disposicion/selecciones/pendientes')->assertOk()->json('data');
        $this->assertSame(5, collect($pendientes)->firstWhere('trd_id', $serie)['expedientes']);

        $lote = $this->proponer($serie);
        $this->assertSame(['sistematico', 2, 'propuesta'], [$lote['metodo'], $lote['intervalo'], $lote['estado']]);
        // Ordenados por fecha de cierre: se proponen el 1.º, el 3.º y el 5.º
        $this->assertSame([true, false, true, false, true], array_column($lote['items'], 'propuesto'));
        $this->assertSame($ids, array_column($lote['items'], 'expediente_id'));

        // Un expediente en una propuesta no entra en otra
        $this->postJson('/api/archivo/disposicion/selecciones', ['trd_id' => $serie])->assertUnprocessable();

        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/confirmar")->assertOk();
        $destinos = DB::table('ofi_archivo_expedientes')->whereIn('id', $ids)->pluck('destino_disposicion', 'id');
        $this->assertSame(['conservar', 'eliminar', 'conservar', 'eliminar', 'conservar'], array_map(fn ($id) => $destinos[$id], $ids));
        $this->assertSame(1, DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $ids[1])->where('accion', 'seleccion_documental')->count());

        // Confirmada ya no se ajusta
        $this->patchJson("/api/archivo/disposicion/selecciones/{$lote['id']}/items/{$lote['items'][1]['id']}", ['seleccionado' => true])->assertUnprocessable();
    }

    public function test_la_muestra_aleatoria_se_reproduce_y_ajustarla_exige_justificacion(): void
    {
        $serie = $this->serie(['s' => 1, 'metodo_seleccion' => 'aleatorio', 'porcentaje_seleccion' => 40]);
        $ids = array_map(fn ($n) => $this->expediente($serie, 6 + $n / 10), range(1, 5));

        $lote = $this->proponer($serie);
        $propuestos = array_keys(array_filter(array_column($lote['items'], 'propuesto')));
        $this->assertCount(2, $propuestos);
        // Con la semilla guardada se obtiene la misma muestra
        $orden = (new Randomizer(new Mt19937($lote['semilla'])))->shuffleArray(range(1, 5));
        $this->assertEqualsCanonicalizing(array_slice($orden, 0, 2), array_map(fn ($i) => $i + 1, $propuestos));

        $otro = collect($lote['items'])->firstWhere('propuesto', false);
        $this->patchJson("/api/archivo/disposicion/selecciones/{$lote['id']}/items/{$otro['id']}", ['seleccionado' => true])->assertOk();
        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/confirmar", ['justificacion' => 'corto'])
            ->assertUnprocessable()->assertJsonValidationErrors('justificacion');
        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/confirmar", ['justificacion' => 'Tiene valor histórico para la entidad'])
            ->assertOk()->assertJsonPath('data.estado', 'confirmada');

        $this->assertSame('conservar', DB::table('ofi_archivo_expedientes')->where('id', $otro['expediente_id'])->value('destino_disposicion'));
        $this->assertSame(3, DB::table('ofi_archivo_expedientes')->whereIn('id', $ids)->where('destino_disposicion', 'conservar')->count());
    }

    public function test_la_cualitativa_no_propone_y_exige_el_criterio_y_la_propuesta_se_puede_anular(): void
    {
        $serie = $this->serie(['s' => 1, 'metodo_seleccion' => 'cualitativo']);
        $this->expediente($serie);
        $this->expediente($serie);

        $lote = $this->proponer($serie);
        $this->assertSame([false, false], array_column($lote['items'], 'propuesto'));

        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/anular")->assertOk();
        $lote = $this->postJson('/api/archivo/disposicion/selecciones', ['trd_id' => $serie])->assertCreated()->json('data');

        $this->patchJson("/api/archivo/disposicion/selecciones/{$lote['id']}/items/{$lote['items'][0]['id']}", ['seleccionado' => true])->assertOk();
        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/confirmar")->assertUnprocessable()->assertJsonValidationErrors('justificacion');
        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/confirmar", ['justificacion' => 'Se conserva el del primer director'])->assertOk();
    }

    public function test_la_secundaria_y_la_eliminacion_respetan_la_seleccion(): void
    {
        $serie = $this->serie(['s' => 1, 'metodo_seleccion' => 'sistematico', 'intervalo_seleccion' => 2]);
        [$primero, $segundo] = [$this->expediente($serie, 6.2), $this->expediente($serie, 6.1)];
        $lotes = app(TransferenciaLotesService::class);

        $this->servicio()->marcarCumplidos();
        $this->assertStringContainsString('aún no se ha confirmado la selección', $lotes->validarExpediente($this->exp($primero), 'secundaria'));

        $lote = $this->proponer($serie);
        $this->postJson("/api/archivo/disposicion/selecciones/{$lote['id']}/confirmar")->assertOk();

        $this->assertNull($lotes->validarExpediente($this->exp($primero), 'secundaria'));
        $this->assertSame('No quedó seleccionado para conservación', $lotes->validarExpediente($this->exp($segundo), 'secundaria'));
        $this->assertSame('Quedó seleccionado para conservación', $this->servicio()->motivoNoPuede($this->exp($primero), DisposicionFinalService::ELIMINAR));
        $this->assertNull($this->servicio()->motivoNoPuede($this->exp($segundo), DisposicionFinalService::ELIMINAR));
    }

    public function test_la_digitalizacion_queda_con_huella_y_certificado_y_es_requisito_de_la_disposicion(): void
    {
        $exp = $this->expediente($this->serie(['ct' => 1, 'm_d' => 1]), 6, ['folios_fisicos' => 120]);
        $sinMd = $this->expediente($this->serie(['ct' => 1]));
        $lotes = app(TransferenciaLotesService::class);
        $archivo = fn () => UploadedFile::fake()->createWithContent('historia.pdf', self::PDF);

        // Antes de la disposición no se digitaliza
        $this->actingAs($this->archivo)->post("/api/archivo/disposicion/expedientes/{$exp}/digitalizaciones", ['archivo' => $archivo()], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->servicio()->marcarCumplidos();
        $this->assertStringContainsString('digitalización (M/D)', $lotes->validarExpediente($this->exp($exp), 'secundaria'));
        $fila = collect($this->getJson('/api/archivo/disposicion/expedientes')->assertOk()->json('data'))->firstWhere('id', $exp);
        $this->assertSame(['CT', 'conservar', 'digitalizacion'], [$fila['disposicion'], $fila['destino'], $fila['pendiente']]);

        $this->post("/api/archivo/disposicion/expedientes/{$sinMd}/digitalizaciones", ['archivo' => $archivo()], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $d = $this->post("/api/archivo/disposicion/expedientes/{$exp}/digitalizaciones", [
            'archivo' => $archivo(), 'paginas' => 120, 'resolucion_dpi' => 300,
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->assertSame(hash('sha256', self::PDF), $d['hash_sha256']);
        $this->assertArrayNotHasKey('certificado', $d);
        $this->assertNull($lotes->validarExpediente($this->exp($exp), 'secundaria'));

        $certificado = $this->get("/api/archivo/disposicion/digitalizaciones/{$d['id']}/certificado")->assertOk();
        $this->assertStringStartsWith('%PDF', $certificado->getContent());
        $lista = $this->getJson("/api/archivo/disposicion/expedientes/{$exp}/digitalizaciones")->assertOk()->json('data');
        $this->assertTrue($lista[0]['verificacion']['integro']);
        $this->assertSame(1, DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $exp)->where('accion', 'digitalizacion')->count());

        // La copia alterada no supera la verificación y el registro no se modifica
        $path = DB::table('ofi_archivo_digitalizaciones')->where('id', $d['id'])->value('archivo_path');
        Storage::disk('archivo_expedientes')->put($path, 'otro contenido');
        $lista = $this->getJson("/api/archivo/disposicion/expedientes/{$exp}/digitalizaciones")->json('data');
        $this->assertContains('La copia digital no coincide con su huella', $lista[0]['verificacion']['problemas']);
        if (DB::getDriverName() === 'mysql') {
            $this->expectExceptionMessage('inmutable');
            DB::table('ofi_archivo_digitalizaciones')->where('id', $d['id'])->update(['hash_sha256' => str_repeat('0', 64)]);
        }
    }

    public function test_sin_permiso_no_selecciona_ni_digitaliza(): void
    {
        $lector = User::factory()->create(['estado' => 1]);
        $lector->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Disposicion -> Ver', 'web'));
        $exp = $this->expediente($this->serie(['ct' => 1, 'm_d' => 1]));

        $this->actingAs($lector)->getJson('/api/archivo/disposicion/selecciones')->assertOk();
        $this->postJson('/api/archivo/disposicion/selecciones', ['trd_id' => 1])->assertForbidden();
        $this->post("/api/archivo/disposicion/expedientes/{$exp}/digitalizaciones", [], ['Accept' => 'application/json'])->assertForbidden();
    }
}
