<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoEliminacionLote;
use App\Models\User;
use App\Services\Configuracion\BusinessDaysService;
use App\Services\OfiArchivo\DisposicionFinalService;
use App\Services\OfiArchivo\EliminacionDocumentalService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B18 (diseño de expedientes §3.8 E): la eliminación era un registro suelto por expediente, sin
 * RUSD, sin publicación, sin comité ni acta firmada, y no borraba ningún binario.
 */
class EliminacionDocumentalTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivo;

    private User $comite;

    private User $ejecutor;

    private User $presidente;

    private User $secretario;

    private int $dependencia;

    private int $version;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archivo_expedientes');

        $permisos = fn (User $u, array $acciones) => collect($acciones)
            ->each(fn ($a) => $u->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> '.$a, 'web')));
        $this->archivo = User::factory()->create(['estado' => 1]);
        $permisos($this->archivo, ['Eliminaciones -> Ver', 'Eliminaciones -> Proponer', 'Eliminaciones -> Ejecutar', 'Expedientes -> Ver Todos']);
        $this->comite = User::factory()->create(['estado' => 1]);
        $permisos($this->comite, ['Eliminaciones -> Ver', 'Eliminaciones -> Registrar comite']);
        $this->ejecutor = User::factory()->create(['estado' => 1]);
        $permisos($this->ejecutor, ['Eliminaciones -> Ver', 'Eliminaciones -> Ejecutar']);
        $this->presidente = User::factory()->create(['estado' => 1, 'nombres' => 'Paula', 'apellidos' => 'Presidente']);
        $this->secretario = User::factory()->create(['estado' => 1, 'nombres' => 'Sergio', 'apellidos' => 'Secretario']);

        $this->dependencia = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Oficina B18', 'cod_organico' => 'B18',
        ]);
        $this->version = DB::table('clasificacion_documental_trd_versiones')->insertGetId([
            'dependencia_id' => $this->dependencia, 'dependencia_nom' => 'Oficina B18', 'version' => 3, 'estado_version' => 'ACTIVO',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function serie(array $disposicion = ['e' => 1]): int
    {
        return DB::table('clasificacion_documental_trd')->insertGetId($disposicion + [
            'tipo' => 'Serie', 'cod' => 'E'.random_int(1000, 9999), 'nom' => 'Comprobantes de egreso', 'user_register' => $this->archivo->id,
            'dependencia_id' => $this->dependencia, 'version_id' => $this->version,
            'a_g' => '2', 'a_c' => '3', 'ct' => 0, 'e' => 0, 'm_d' => 0, 's' => 0,
        ]);
    }

    /** Expediente cerrado en el central hace 6 años, con sus folios en el disco. */
    private function expediente(int $serie, array $archivos = ['propio'], array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ofi_archivo_expedientes')->insertGetId($extra + [
            'numero_expediente' => 'B18-'.uniqid(), 'nombre_expediente' => 'Egresos de caja', 'anio' => 2018,
            'dependencia_id' => $this->dependencia, 'dependencia_cod' => 'B18', 'dependencia_nom' => 'Oficina B18',
            'serie_trd_id' => $serie, 'estado' => 'abierto', 'fase' => 'central', 'fecha_apertura' => now()->subYears(8),
            'usuario_apertura_id' => $this->archivo->id, 'fecha_cierre' => now()->subYears(6),
        ]);
        foreach ($archivos as $i => $nombre) {
            $path = $nombre === 'propio' ? "exp/{$id}/folio{$i}.pdf" : $nombre;
            Storage::disk('archivo_expedientes')->put($path, "contenido {$path}");
            DB::table('ofi_archivo_expedientes_documentos')->insert([
                'expediente_id' => $id, 'numero_folio' => $i + 1, 'orden' => $i, 'tipo_documental' => 'Comprobante',
                'fecha_documento' => '2018-03-0'.($i + 1), 'asunto' => 'Folio', 'usuario_id' => $this->archivo->id,
                'archivo_path' => $path, 'activo' => true,
            ]);
        }
        DB::table('ofi_archivo_expedientes')->where('id', $id)->update(['estado' => 'cerrado']);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function rusd(): void
    {
        DB::table('clasificacion_documental_trd_versiones')->where('id', $this->version)
            ->update(['rusd_inscrita' => true, 'rusd_numero' => 'RUSD-2025-77', 'rusd_fecha' => '2025-05-02']);
    }

    private function proponer(int $serie): array
    {
        app(DisposicionFinalService::class)->marcarCumplidos();

        return $this->actingAs($this->archivo)->postJson('/api/archivo/eliminaciones/lotes', ['trd_id' => $serie])
            ->assertCreated()->json('data');
    }

    /** Lleva el lote hasta 'por_ejecutar': cierre sin observaciones, comité y las dos firmas. */
    private function aprobarYFirmar(array $lote): void
    {
        $this->travelTo(now()->addMonths(4));
        app(EliminacionDocumentalService::class)->cerrarPublicaciones();
        $this->actingAs($this->comite)->postJson("/api/archivo/eliminaciones/lotes/{$lote['id']}/comite", [
            'acta_comite_numero' => '012-2026', 'acta_comite_fecha' => today()->toDateString(), 'metodo' => 'borrado_seguro',
            'presidente_id' => $this->presidente->id, 'secretario_id' => $this->secretario->id,
        ])->assertOk();
        $this->actingAs($this->presidente)->postJson("/api/archivo/eliminaciones/lotes/{$lote['id']}/firmar")->assertOk();
        $this->actingAs($this->secretario)->postJson("/api/archivo/eliminaciones/lotes/{$lote['id']}/firmar")->assertOk();
    }

    public function test_sin_rusd_no_se_propone_y_la_version_aprobada_lo_registra(): void
    {
        $serie = $this->serie();
        $this->expediente($serie);
        app(DisposicionFinalService::class)->marcarCumplidos();

        $pendiente = $this->actingAs($this->archivo)->getJson('/api/archivo/eliminaciones/lotes/pendientes')->assertOk()->json('data.0');
        $this->assertSame([$serie, 1, $this->version], [$pendiente['trd_id'], $pendiente['expedientes'], $pendiente['version_id']]);
        $this->assertStringContainsString('no está inscrita en el RUSD', $pendiente['motivo']);
        $this->actingAs($this->archivo)->postJson('/api/archivo/eliminaciones/lotes', ['trd_id' => $serie])
            ->assertStatus(422)->assertJsonFragment(['trd_id' => ['La TRD versión 3 de Oficina B18 no está inscrita en el RUSD']]);

        $admin = User::factory()->create(['estado' => 1]);
        $admin->givePermissionTo(Permission::findOrCreate('TRD -> Versiones', 'web'));
        $this->actingAs($admin)->patchJson("/api/clasifica-documental/trd-versiones/{$this->version}/rusd", ['rusd_inscrita' => true])
            ->assertStatus(422)->assertJsonValidationErrors(['rusd_numero', 'rusd_fecha']);
        $this->actingAs($admin)->patchJson("/api/clasifica-documental/trd-versiones/{$this->version}/rusd", [
            'rusd_inscrita' => true, 'rusd_numero' => 'RUSD-2025-77', 'rusd_fecha' => '2025-05-02',
        ])->assertOk();

        $this->assertNull($this->actingAs($this->archivo)->getJson('/api/archivo/eliminaciones/lotes/pendientes')->json('data.0.motivo'));
    }

    public function test_propone_la_serie_completa_y_publica_60_dias_habiles(): void
    {
        $this->rusd();
        $serie = $this->serie();
        $a = $this->expediente($serie);
        $b = $this->expediente($serie, ['propio'], ['nivel_acceso' => 'reservado', 'nombre_expediente' => 'Pagos a informantes']);
        $conservacion = $this->expediente($this->serie(['ct' => 1]));

        $lote = $this->proponer($serie);

        $this->assertMatchesRegularExpression('/^EL-\d{4}-0001$/', $lote['codigo']);
        $this->assertSame(OfiArchivoEliminacionLote::PUBLICADO, $lote['estado']);
        $this->assertSame('RUSD-2025-77', $lote['rusd_numero']);
        $this->assertSame(app(BusinessDaysService::class)->calcularVencimiento(now(), 60)->toDateString(), $lote['fin_publicacion']);
        $this->assertEqualsCanonicalizing([$a, $b], array_column($lote['items'], 'expediente_id'));
        $this->assertSame('Información reservado (Ley 1712 de 2014)', collect($lote['items'])->firstWhere('expediente_id', $b)['nombre_publico']);
        $this->assertStringStartsWith('%PDF', OfiArchivoEliminacionLote::find($lote['id'])->getRawOriginal('inventario'));
        $this->assertSame('propuesta_eliminacion', DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $a)->latest('secuencia')->value('accion'));

        // Ya están en un lote activo; el de conservación nunca entra
        $this->actingAs($this->archivo)->postJson('/api/archivo/eliminaciones/lotes', ['trd_id' => $serie])->assertStatus(422);
        $this->assertNotContains($conservacion, DB::table('ofi_archivo_eliminacion_items')->pluck('expediente_id')->all());
        // Sin el permiso de proponer
        $this->actingAs($this->comite)->postJson('/api/archivo/eliminaciones/lotes', ['trd_id' => $serie])->assertForbidden();
    }

    public function test_la_pagina_publica_muestra_el_inventario_y_recibe_observaciones(): void
    {
        $this->rusd();
        $serie = $this->serie();
        $numero = DB::table('ofi_archivo_expedientes')->where('id', $this->expediente($serie))->value('numero_expediente');
        $lote = $this->proponer($serie);
        app('auth')->forgetGuards();
        $base = "/api/publico/eliminaciones/{$lote['codigo']}";

        $this->getJson('/api/publico/eliminaciones?vigencia=abiertos')->assertOk()
            ->assertJsonPath('data.0.codigo', $lote['codigo'])->assertJsonPath('data.0.items_count', 1)
            ->assertJsonMissingPath('data.0.user_id');
        $this->getJson($base)->assertOk()->assertJsonPath('data.items.0.numero_expediente', $numero)->assertJsonPath('data.abierto_observaciones', true);
        $this->get("{$base}/inventario")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("{$base}/acta")->assertNotFound();

        $observacion = ['nombre' => 'Lucía Ciudadana', 'correo' => 'lucia@example.com', 'expediente' => $numero, 'observacion' => 'Estos comprobantes prueban mi pensión.'];
        $this->postJson("{$base}/observaciones", $observacion + ['sitio_web' => 'http://spam'])->assertStatus(422);
        $this->postJson("{$base}/observaciones", ['expediente' => 'OTRO-1'] + $observacion)->assertStatus(422)->assertJsonValidationErrors('expediente');
        $this->postJson("{$base}/observaciones", $observacion)->assertCreated();
        $this->assertSame($numero, DB::table('ofi_archivo_eliminacion_observaciones as o')
            ->join('ofi_archivo_eliminacion_items as i', 'i.id', '=', 'o.item_id')->where('o.lote_id', $lote['id'])->value('i.numero_expediente'));

        // Vencida la publicación ya no recibe
        $this->travelTo(now()->addMonths(4));
        $this->postJson("{$base}/observaciones", $observacion)->assertStatus(422);
        // Un lote anulado deja de publicarse
        DB::table('ofi_archivo_eliminacion_lotes')->where('id', $lote['id'])->update(['estado' => 'anulado']);
        $this->getJson($base)->assertNotFound();
    }

    public function test_con_observaciones_espera_concepto_y_suspender_libera_los_expedientes(): void
    {
        $this->rusd();
        $serie = $this->serie();
        $this->expediente($serie);
        $lote = $this->proponer($serie);
        DB::table('ofi_archivo_eliminacion_observaciones')->insert(['lote_id' => $lote['id'], 'nombre' => 'X', 'observacion' => 'Tiene valor', 'created_at' => now()]);

        $this->travelTo(now()->addMonths(4));
        $this->artisan('archivo:cerrar-publicaciones-eliminacion')->assertSuccessful();

        $cerrado = OfiArchivoEliminacionLote::find($lote['id']);
        $this->assertSame(OfiArchivoEliminacionLote::EN_CONCEPTO, $cerrado->estado);
        $this->assertSame(app(BusinessDaysService::class)->calcularVencimiento($cerrado->fin_publicacion->copy(), 30)->toDateString(), $cerrado->fin_concepto->toDateString());
        $this->assertTrue(Notificacion::where('user_id', $this->comite->id)->where('type', 'eliminacion_documental')->exists());

        // El comité aún no puede aprobar: falta el concepto
        $this->actingAs($this->comite)->postJson("/api/archivo/eliminaciones/lotes/{$lote['id']}/comite", [
            'acta_comite_numero' => '1', 'acta_comite_fecha' => today()->toDateString(), 'metodo' => 'borrado_seguro',
            'presidente_id' => $this->presidente->id, 'secretario_id' => $this->secretario->id,
        ])->assertStatus(422);
        $this->actingAs($this->comite)->postJson("/api/archivo/eliminaciones/lotes/{$lote['id']}/concepto", [
            'decision' => 'ampliar_retencion', 'concepto' => 'Se amplía la retención por valor probatorio.',
        ])->assertOk()->assertJsonPath('data.estado', 'suspendido');

        $this->assertSame(1, $this->actingAs($this->archivo)->getJson('/api/archivo/eliminaciones/lotes/pendientes')->json('data.0.expedientes'));
    }

    public function test_comite_firmas_y_ejecucion_borran_lo_propio_y_dejan_el_acta(): void
    {
        $this->rusd();
        $serie = $this->serie();
        $compartido = 'exp/compartido.pdf';
        $exp = $this->expediente($serie, ['propio', $compartido]);
        $otro = $this->expediente($this->serie(['ct' => 1]), [$compartido]);
        $propio = DB::table('ofi_archivo_expedientes_documentos')->where('expediente_id', $exp)->where('archivo_path', '<>', $compartido)->value('archivo_path');
        $lote = $this->proponer($serie);
        $id = $lote['id'];

        $this->travelTo(now()->addMonths(4));
        app(EliminacionDocumentalService::class)->cerrarPublicaciones();
        $comite = ['acta_comite_numero' => '012-2026', 'acta_comite_fecha' => today()->toDateString(), 'metodo' => 'borrado_seguro',
            'presidente_id' => $this->presidente->id, 'secretario_id' => $this->presidente->id];
        $this->actingAs($this->comite)->postJson("/api/archivo/eliminaciones/lotes/{$id}/comite", $comite)->assertStatus(422);
        $this->actingAs($this->comite)->postJson("/api/archivo/eliminaciones/lotes/{$id}/comite", ['secretario_id' => $this->secretario->id] + $comite)
            ->assertOk()->assertJsonPath('data.estado', 'aprobado');
        $this->assertTrue(Notificacion::where('user_id', $this->secretario->id)->where('title', 'Acta de eliminación por firmar')->exists());

        // Solo firman los designados, una vez cada uno
        $this->actingAs($this->ejecutor)->postJson("/api/archivo/eliminaciones/lotes/{$id}/firmar")->assertStatus(422);
        $this->actingAs($this->presidente)->getJson('/api/archivo/eliminaciones/lotes/por-firmar')->assertOk()->assertJsonPath('data.0.rol', 'presidente');
        $this->actingAs($this->presidente)->postJson("/api/archivo/eliminaciones/lotes/{$id}/firmar")->assertOk()->assertJsonPath('data.estado', 'aprobado');
        $this->actingAs($this->presidente)->postJson("/api/archivo/eliminaciones/lotes/{$id}/firmar")->assertStatus(422);
        $this->actingAs($this->secretario)->postJson("/api/archivo/eliminaciones/lotes/{$id}/firmar")->assertOk()->assertJsonPath('data.estado', 'por_ejecutar');

        // Quien propuso no ejecuta
        $this->actingAs($this->archivo)->postJson("/api/archivo/eliminaciones/lotes/{$id}/ejecutar")->assertStatus(422)
            ->assertJsonFragment(['lote' => ['Quien propuso la eliminación no puede ejecutarla']]);
        $this->actingAs($this->ejecutor)->postJson("/api/archivo/eliminaciones/lotes/{$id}/ejecutar")->assertOk()
            ->assertJsonPath('data.estado', 'ejecutado')->assertJsonPath('data.items.0.archivos_borrados', 1)
            ->assertJsonPath('data.items.0.archivos_compartidos', 1);

        $this->assertSame('eliminado', DB::table('ofi_archivo_expedientes')->where('id', $exp)->value('estado'));
        $this->assertSame('en_disposicion', DB::table('ofi_archivo_expedientes')->where('id', $otro)->value('estado'));
        Storage::disk('archivo_expedientes')->assertMissing($propio);
        Storage::disk('archivo_expedientes')->assertExists($compartido);
        $bitacora = DB::table('ofi_archivo_depuraciones_digitales')->where('accion', 'eliminado')->where('ruta_original', $propio)->first();
        $this->assertSame(hash('sha256', "contenido {$propio}"), $bitacora->hash_sha256);
        $this->assertStringContainsString($lote['codigo'], $bitacora->origen);
        // La metadata y los folios siguen: solo se fue el binario
        $this->assertSame(2, DB::table('ofi_archivo_expedientes_documentos')->where('expediente_id', $exp)->count());

        $acta = OfiArchivoEliminacionLote::find($id);
        $this->assertStringStartsWith('%PDF', $acta->getRawOriginal('acta'));
        $this->assertSame(hash('sha256', $acta->getRawOriginal('acta')), $acta->hash_acta);
        app('auth')->forgetGuards();
        $this->get("/api/publico/eliminaciones/{$lote['codigo']}/acta")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::table('ofi_archivo_eliminacion_lotes')->where('id', $id)->update(['metodo' => 'mixto']);
                $this->fail('El lote ejecutado no debía modificarse');
            } catch (QueryException $e) {
                $this->assertStringContainsString('inmutables', $e->getMessage());
            }
        }
    }

    public function test_si_cambia_algo_antes_de_ejecutar_no_elimina_nada(): void
    {
        $this->rusd();
        $serie = $this->serie();
        $exp = $this->expediente($serie);
        $lote = $this->proponer($serie);
        $this->aprobarYFirmar($lote);

        DB::table('ofi_archivo_prestamos')->insert([
            'expediente_id' => $exp, 'solicitante_id' => $this->comite->id, 'fecha_prestamo' => now(),
            'fecha_devolucion_esperada' => now()->addDay(), 'estado' => 'prestado', 'usuario_registro_id' => $this->comite->id,
        ]);
        $this->actingAs($this->ejecutor)->postJson("/api/archivo/eliminaciones/lotes/{$lote['id']}/ejecutar")->assertStatus(422);

        $this->assertSame('en_disposicion', DB::table('ofi_archivo_expedientes')->where('id', $exp)->value('estado'));
        $this->assertSame('por_ejecutar', DB::table('ofi_archivo_eliminacion_lotes')->where('id', $lote['id'])->value('estado'));
        Storage::disk('archivo_expedientes')->assertExists(DB::table('ofi_archivo_expedientes_documentos')->where('expediente_id', $exp)->value('archivo_path'));
    }
}
