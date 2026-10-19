<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoIntegridadIncidente;
use App\Models\User;
use App\Services\OfiArchivo\IntegridadArchivoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B21 (diseño de expedientes §8): nadie volvía a comprobar que los binarios de los folios siguieran
 * coincidiendo con su huella después de incorporarlos.
 */
class IntegridadArchivoTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivo;

    private User $responsable;

    private int $orden = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archivo_expedientes');

        [$this->archivo, $this->responsable] = User::factory()->count(2)->create(['estado' => 1])->all();
        foreach (['Ver', 'Resolver'] as $accion) {
            $this->archivo->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Integridad -> '.$accion, 'web'));
        }
        $this->archivo->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver Todos', 'web'));
    }

    private function expediente(): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'INT-'.uniqid(), 'nombre_expediente' => 'Expediente integridad', 'anio' => 2026,
            'dependencia_id' => 1, 'serie_trd_id' => 1, 'estado' => 'abierto', 'fecha_apertura' => now()->subMonth(),
            'usuario_apertura_id' => $this->responsable->id,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    /** Folio con archivo (o sin él) y la huella indicada; true = la del contenido. */
    private function folio(int $expediente, ?string $contenido, string|bool|null $hash = true): OfiArchivoExpedienteDocumento
    {
        $path = $contenido !== null ? "exp{$expediente}/".uniqid().'.pdf' : null;
        if ($path) {
            Storage::disk('archivo_expedientes')->put($path, $contenido);
        }

        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $expediente, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Acta', 'orden' => ++$this->orden,
            'fecha_documento' => now()->toDateString(), 'asunto' => 'Folio', 'archivo_path' => $path,
            'nombre_original' => $path ? basename($path) : null, 'detalle' => $path ? 'Archivo' : 'Folio físico',
            'hash_sha256' => $hash === true ? hash('sha256', (string) $contenido) : ($hash ?: null),
            'usuario_id' => $this->archivo->id,
        ]);
    }

    private function resultados(int $verificacion): array
    {
        return DB::table('ofi_archivo_integridad_revisiones')->where('verificacion_id', $verificacion)
            ->pluck('resultado', 'documento_id')->all();
    }

    public function test_clasifica_los_folios_abre_un_incidente_por_folio_y_avisa(): void
    {
        $exp = $this->expediente();
        $integro = $this->folio($exp, 'original');
        $alterado = $this->folio($exp, 'original');
        Storage::disk('archivo_expedientes')->put($alterado->archivo_path, 'modificado');
        $faltante = $this->folio($exp, 'se pierde');
        Storage::disk('archivo_expedientes')->delete($faltante->archivo_path);
        $sinHuella = $this->folio($exp, 'sin huella', null);
        $fisico = $this->folio($exp, null, null);
        DB::table('ofi_archivo_expedientes')->where('id', $exp)->update(['estado' => 'cerrado']);

        $verificacion = $this->actingAs($this->archivo)->postJson("/api/archivo/integridad/expedientes/{$exp}/verificar")
            ->assertOk()->assertJsonPath('data.revisados', 5)->assertJsonPath('data.incidentes', 3)->json('data.id');

        $this->assertSame([
            $integro->id => 'integro', $alterado->id => 'alterado', $faltante->id => 'faltante',
            $sinHuella->id => 'sin_huella', $fisico->id => 'sin_archivo',
        ], $this->resultados($verificacion));
        $this->assertEqualsCanonicalizing(
            [[$alterado->id, 'alterado'], [$faltante->id, 'faltante'], [$sinHuella->id, 'sin_huella']],
            OfiArchivoIntegridadIncidente::where('expediente_id', $exp)->get()->map(fn ($i) => [$i->documento_id, $i->tipo])->all()
        );
        $this->assertSame(3, DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $exp)
            ->where('accion', IntegridadArchivoService::EVENTO_INCIDENTE)->count());
        foreach ([$this->archivo, $this->responsable] as $user) {
            $aviso = Notificacion::where('user_id', $user->id)->where('type', IntegridadArchivoService::TIPO_NOTIFICACION)->sole();
            $this->assertStringContainsString('3 folio(s)', $aviso->message);
        }

        // Volver a verificar no duplica los incidentes abiertos ni los avisos
        $this->actingAs($this->archivo)->postJson("/api/archivo/integridad/expedientes/{$exp}/verificar")
            ->assertOk()->assertJsonPath('data.incidentes', 3);
        $this->assertSame(3, OfiArchivoIntegridadIncidente::where('expediente_id', $exp)->count());
        $this->assertSame(1, Notificacion::where('user_id', $this->archivo->id)->where('type', IntegridadArchivoService::TIPO_NOTIFICACION)->count());
    }

    public function test_la_muestra_empieza_por_los_folios_nunca_verificados(): void
    {
        $verificado = $this->folio($this->expediente(), 'ya revisado');
        app(IntegridadArchivoService::class)->verificarExpediente($verificado->expediente);
        $exp = $this->expediente();
        $nuevos = collect(range(1, 3))->map(fn ($n) => $this->folio($exp, "nuevo {$n}")->id);

        $this->artisan('archivo:verificar-integridad', ['--porcentaje' => 50])->assertSuccessful();

        $verificacion = DB::table('ofi_archivo_integridad_verificaciones')->where('alcance', 'muestra')->latest('id')->first();
        $this->assertSame(50, (int) $verificacion->porcentaje);
        $revisados = array_keys($this->resultados($verificacion->id));
        $this->assertNotEmpty($revisados);
        $this->assertNotContains($verificado->id, $revisados);

        // Con el 100 % entran todos, también el ya revisado
        $this->artisan('archivo:verificar-integridad', ['--porcentaje' => 100])->assertSuccessful();
        $todos = array_keys($this->resultados(DB::table('ofi_archivo_integridad_verificaciones')->max('id')));
        $this->assertEmpty(array_diff([$verificado->id, ...$nuevos], $todos));

        $this->artisan('archivo:verificar-integridad', ['--porcentaje' => 0])->assertFailed();
    }

    public function test_resolver_exige_justificacion_y_registra_el_resultado_de_volver_a_verificar(): void
    {
        $exp = $this->expediente();
        $doc = $this->folio($exp, 'original');
        Storage::disk('archivo_expedientes')->put($doc->archivo_path, 'modificado');
        app(IntegridadArchivoService::class)->verificarExpediente($doc->expediente);
        $incidente = OfiArchivoIntegridadIncidente::where('documento_id', $doc->id)->sole();

        $this->actingAs($this->archivo)->getJson('/api/archivo/integridad/incidentes?estado=abierto')
            ->assertOk()->assertJsonFragment(['id' => $incidente->id, 'tipo' => 'alterado']);
        $this->actingAs($this->responsable)->getJson('/api/archivo/integridad/incidentes')->assertForbidden();
        $this->actingAs($this->responsable)->postJson("/api/archivo/integridad/incidentes/{$incidente->id}/resolver", [
            'resolucion' => 'Restaurado desde la copia de respaldo',
        ])->assertForbidden();
        $this->actingAs($this->archivo)->postJson("/api/archivo/integridad/incidentes/{$incidente->id}/resolver", ['resolucion' => 'corto'])
            ->assertUnprocessable()->assertJsonValidationErrors('resolucion');

        // Se restaura el archivo y se resuelve: queda el resultado de verificarlo de nuevo
        Storage::disk('archivo_expedientes')->put($doc->archivo_path, 'original');
        $this->actingAs($this->archivo)->postJson("/api/archivo/integridad/incidentes/{$incidente->id}/resolver", [
            'resolucion' => 'Restaurado desde la copia de respaldo',
        ])->assertOk()->assertJsonPath('data.estado', 'resuelto')->assertJsonPath('data.resultado_al_resolver', 'integro');
        $this->assertSame(1, DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $exp)
            ->where('accion', IntegridadArchivoService::EVENTO_RESUELTO)->count());

        $this->actingAs($this->archivo)->postJson("/api/archivo/integridad/incidentes/{$incidente->id}/resolver", [
            'resolucion' => 'Restaurado desde la copia de respaldo',
        ])->assertUnprocessable();
        $this->actingAs($this->archivo)->getJson('/api/archivo/integridad/verificaciones?alcance=expediente')
            ->assertOk()->assertJsonFragment(['expediente_id' => $exp]);
    }

    public function test_no_verifica_un_expediente_que_el_usuario_no_ve(): void
    {
        $exp = $this->expediente();
        $sinAcceso = User::factory()->create(['estado' => 1]);
        $sinAcceso->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Integridad -> Resolver', 'web'));

        $this->actingAs($sinAcceso)->postJson("/api/archivo/integridad/expedientes/{$exp}/verificar")->assertNotFound();
    }
}
