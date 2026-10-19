<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use App\Models\User;
use App\Services\OfiArchivo\ExpedienteEventosService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B12 (diseño de expedientes): el historial del expediente es de solo inserción y encadena
 * la huella de cada evento con la del anterior.
 */
class HistorialExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ofi_archivo_expediente_eventos')) {
            $this->markTestSkipped('Requiere la migración 2026_10_19_100000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Ver Todos', 'Ver', 'Cerrar', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep H', 'cod_organico' => 'B12D']);
        $serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'B12']);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B12D-B12-'.uniqid(),
            'nombre_expediente' => 'Expediente B12',
            'anio' => 2026,
            'dependencia_id' => $depId,
            'serie_trd_id' => $serieId,
            'estado' => 'abierto',
            'fecha_apertura' => '2026-10-19',
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function documento(): OfiArchivoExpedienteDocumento
    {
        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $this->expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Acta',
            'asunto' => 'Acta de reunión', 'fecha_documento' => '2026-10-19', 'usuario_id' => $this->user->id,
            'hash_sha256' => hash('sha256', 'acta'), 'activo' => true, 'orden' => 0,
        ]);
    }

    public function test_el_ciclo_del_expediente_queda_encadenado_en_su_historial(): void
    {
        $documento = $this->documento();
        $this->expediente->update(['nombre_expediente' => 'Expediente B12 renombrado']);
        $documento->delete();

        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", ['motivo_cierre' => 'Trámite finalizado'])
            ->assertOk();

        $eventos = OfiArchivoExpedienteEvento::where('expediente_id', $this->expediente->id)->orderBy('secuencia')->get();

        $this->assertSame(['apertura', 'incorporacion', 'edicion', 'retiro', 'cierre'], $eventos->pluck('accion')->all());
        $this->assertSame([1, 2, 3, 4, 5], $eventos->pluck('secuencia')->all());
        $this->assertNull($eventos[0]->hash_anterior);
        foreach ($eventos->skip(1) as $i => $evento) {
            $this->assertSame($eventos[$i - 1]->hash, $evento->hash_anterior);
        }
        $this->assertSame($this->user->id, (int) $eventos[0]->usuario_id);

        $this->assertSame(1, $eventos[1]->detalle['numero_folio']);
        $this->assertSame(hash('sha256', 'acta'), $eventos[1]->detalle['hash_sha256']);
        $this->assertSame(
            ['antes' => 'Expediente B12', 'despues' => 'Expediente B12 renombrado'],
            $eventos[2]->detalle['cambios']['nombre_expediente']
        );
        $this->assertSame('Trámite finalizado', $eventos[4]->detalle['cambios']['motivo_cierre']['despues']);
        $this->assertSame($this->expediente->fresh()->hash_indice, $eventos[4]->detalle['hash_indice']);

        $this->assertSame([], app(ExpedienteEventosService::class)->verificar($this->expediente->id));
        $this->artisan('archivo:verificar-cadena', ['--expediente' => $this->expediente->id])->assertSuccessful();

        $this->getJson("/api/archivo/expedientes/{$this->expediente->id}/historial")
            ->assertOk()
            ->assertJsonPath('data.integro', true)
            ->assertJsonCount(5, 'data.eventos')
            ->assertJsonPath('data.eventos.4.accion', 'cierre');
    }

    public function test_la_base_de_datos_no_deja_modificar_ni_borrar_eventos(): void
    {
        $id = OfiArchivoExpedienteEvento::where('expediente_id', $this->expediente->id)->value('id');

        try {
            DB::table('ofi_archivo_expediente_eventos')->where('id', $id)->update(['accion' => 'otra']);
            $this->fail('El trigger debía rechazar el UPDATE');
        } catch (QueryException $e) {
            $this->assertStringContainsString('inmutable', $e->getMessage());
        }

        try {
            DB::table('ofi_archivo_expediente_eventos')->where('id', $id)->delete();
            $this->fail('El trigger debía rechazar el DELETE');
        } catch (QueryException $e) {
            $this->assertStringContainsString('inmutable', $e->getMessage());
        }

        $this->expectException(\LogicException::class);
        OfiArchivoExpedienteEvento::findOrFail($id)->delete();
    }

    public function test_detecta_eventos_insertados_por_fuera_de_la_cadena(): void
    {
        $this->documento();
        // Un evento forjado directamente en la tabla: salta la secuencia y no apunta al anterior
        DB::table('ofi_archivo_expediente_eventos')->insert([
            'expediente_id' => $this->expediente->id, 'secuencia' => 4, 'accion' => 'retiro',
            'detalle' => '{"documento_id":1}', 'ocurrido_en' => now(), 'hash_anterior' => str_repeat('0', 64),
            'hash' => str_repeat('a', 64),
        ]);

        $problemas = array_column(app(ExpedienteEventosService::class)->verificar($this->expediente->id), 'problema');

        $this->assertSame([
            'Falta el evento 3: la secuencia salta a 4',
            'El evento no apunta a la huella del anterior',
            'El contenido del evento no coincide con su huella',
        ], $problemas);
        $this->artisan('archivo:verificar-cadena', ['--expediente' => $this->expediente->id])->assertFailed();

        $this->getJson("/api/archivo/expedientes/{$this->expediente->id}/historial")
            ->assertOk()
            ->assertJsonPath('data.integro', false);
    }
}
