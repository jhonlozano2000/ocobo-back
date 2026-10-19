<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B14 (diseño de expedientes §3.4): el cierre exige que el expediente esté conforme y fija la
 * retención con una instantánea de la TRD vigente en ese momento.
 */
class CierreExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    private int $subserie;

    private int $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ofi_archivo_expedientes', 'trd_snapshot')
            || ! Schema::hasColumn('ofi_archivo_expedientes_documentos', 'tipo_documental_id')
            || ! Schema::hasColumn('workflow_instancias', 'radicado_id')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_18_110000, 2026_10_19_120000 y 2026_10_19_140000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Ver Todos', 'Ver', 'Cerrar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B14', 'cod_organico' => 'B14D']);
        $nodo = fn (array $datos) => DB::table('clasificacion_documental_trd')->insertGetId($datos + [
            'dependencia_id' => $depId, 'user_register' => $this->user->id,
        ]);
        $serie = $nodo(['tipo' => 'Serie', 'cod' => 'S14', 'nom' => 'Contratos', 'a_g' => 9, 'a_c' => 9]);
        $this->subserie = $nodo(['tipo' => 'SubSerie', 'cod' => 'S14.1', 'nom' => 'Contratos de obra', 'parent' => $serie,
            'a_g' => 2, 'a_c' => 3, 'ct' => 1, 'e' => 0, 'm_d' => 0, 's' => 0, 'disposicion_final' => 'CT']);
        $this->tipo = $nodo(['tipo' => 'TipoDocumento', 'cod' => 'T1', 'nom' => 'Acta', 'parent' => $this->subserie]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B14D-S14-'.uniqid(), 'nombre_expediente' => 'Contrato 14', 'anio' => 2026,
            'dependencia_id' => $depId, 'serie_trd_id' => $serie, 'subserie_trd_id' => $this->subserie, 'estado' => 'abierto',
            'soporte' => 'electronico', 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id,
        ]);

        Storage::fake('archivo_expedientes');
    }

    private function folio(array $datos = []): OfiArchivoExpedienteDocumento
    {
        return OfiArchivoExpedienteDocumento::create($datos + [
            'expediente_id' => $this->expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Acta',
            'tipo_documental_id' => $this->tipo, 'asunto' => 'Acta', 'fecha_documento' => '2026-10-19',
            'hash_sha256' => hash('sha256', 'acta'), 'usuario_id' => $this->user->id, 'activo' => true, 'orden' => 0,
        ]);
    }

    private function cerrar(array $datos = [])
    {
        return $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", $datos + ['motivo_cierre' => 'Terminó el trámite']);
    }

    public function test_el_cierre_se_bloquea_hasta_que_el_expediente_esta_conforme(): void
    {
        $this->expediente->update(['soporte' => 'hibrido', 'ubicacion_fisica' => null]);

        Storage::disk('archivo_expedientes')->put('exp/acta.pdf', 'contenido alterado');
        $alterado = $this->folio(['archivo_path' => 'exp/acta.pdf', 'hash_sha256' => hash('sha256', 'contenido original')]);
        $sinTipo = $this->folio(['tipo_documental_id' => null, 'tipo_documental' => 'Documento de flujo']);

        Schema::disableForeignKeyConstraints();
        $radicado = $this->folio(['documentable_type' => VentanillaRadicaReci::class, 'documentable_id' => 991414]);
        $instancia = DB::table('workflow_instancias')->insertGetId([
            'workflow_id' => 1, 'estado' => 'en_curso', 'usuario_ejecuta_id' => $this->user->id, 'radicado_id' => 991414,
            'fecha_inicio' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $prestamo = DB::table('ofi_archivo_prestamos')->insertGetId([
            'expediente_id' => $this->expediente->id, 'solicitante_id' => $this->user->id, 'fecha_prestamo' => now(),
            'fecha_devolucion_esperada' => now()->addDays(5), 'estado' => 'prestado', 'usuario_registro_id' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $impedimentos = $this->cerrar()->assertUnprocessable()->json('errors.cierre');

        $this->assertCount(5, $impedimentos);
        $this->assertStringContainsString('híbrido no tiene ubicación física', $impedimentos[0]);
        $this->assertStringContainsString('1 préstamo(s) sin devolver', $impedimentos[1]);
        $this->assertStringContainsString("instancias {$instancia}", $impedimentos[2]);
        $this->assertSame("Folios sin tipificar con la TRD: {$sinTipo->numero_folio}.", $impedimentos[3]);
        $this->assertStringContainsString((string) $alterado->numero_folio, $impedimentos[4]);
        $this->assertSame('abierto', $this->expediente->fresh()->estado);

        // Resuelto cada impedimento, el expediente se cierra
        $this->expediente->update(['ubicacion_fisica' => 'Archivo de gestión, estante 3']);
        DB::table('ofi_archivo_prestamos')->where('id', $prestamo)->update(['estado' => 'devuelto']);
        DB::table('workflow_instancias')->where('id', $instancia)->update(['estado' => 'completada']);
        $sinTipo->update(['tipo_documental_id' => $this->tipo, 'tipo_documental' => 'Acta']);
        $alterado->delete();
        $this->assertNotNull($radicado->id);

        $this->cerrar()->assertOk();
        $this->assertSame('cerrado', $this->expediente->fresh()->estado);
    }

    public function test_un_expediente_por_completar_no_se_cierra(): void
    {
        $this->expediente->update(['nivel_acceso' => null]);
        $this->folio();

        $this->cerrar()->assertUnprocessable()
            ->assertJsonPath('errors.cierre.0', 'El expediente está por completar; falta declarar: nivel de acceso.');
    }

    public function test_el_cierre_guarda_la_instantanea_de_la_trd_y_las_fechas_de_retencion(): void
    {
        $this->folio();

        $this->cerrar(['fecha_cierre' => now()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_cierre');
        $this->cerrar(['fecha_cierre' => '2025-12-31'])
            ->assertUnprocessable()->assertJsonPath('errors.cierre.0', fn ($m) => str_contains($m, 'anterior a la apertura'));

        $this->cerrar(['fecha_cierre' => '2026-10-15'])->assertOk();

        $expediente = $this->expediente->fresh();
        $this->assertSame('2026-10-15', $expediente->fecha_cierre->toDateString());
        $this->assertSame('2028-10-15', $expediente->fecha_fin_gestion->toDateString());
        $this->assertSame('2031-10-15', $expediente->fecha_fin_central->toDateString());
        $this->assertSame($this->subserie, $expediente->trd_snapshot['trd_id']);
        $this->assertEquals(['a_g' => 2, 'a_c' => 3, 'ct' => true, 'disposicion_final' => 'CT'],
            collect($expediente->trd_snapshot)->only(['a_g', 'a_c', 'ct', 'disposicion_final'])->all());

        // Cambiar la TRD después del cierre no mueve la retención del expediente
        DB::table('clasificacion_documental_trd')->where('id', $this->subserie)->update(['a_g' => 10, 'ct' => 0, 'e' => 1]);
        $expediente = $expediente->fresh();
        $this->assertSame('2028-10-15', $expediente->fecha_retencion_gestion_fin->toDateString());
        $this->assertSame(2, (int) $expediente->trdRetencion()->a_g);
        $this->assertTrue((bool) $expediente->trdRetencion()->ct);
    }
}
