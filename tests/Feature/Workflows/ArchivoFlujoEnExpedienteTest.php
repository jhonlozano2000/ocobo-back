<?php

namespace Tests\Feature\Workflows;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkFlowArchivo;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M19-30: al completarse una instancia iniciada desde un radicado, sus archivos y las evidencias
 * de sus tareas se incorporan como folios al expediente abierto del radicado.
 */
class ArchivoFlujoEnExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    private Workflow $workflow;

    private WorkflowNodo $paso;

    private int $radicadoId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_instancias', 'radicado_id') || ! Schema::hasColumn('workflow_tareas', 'instancia_id')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_17_120000 y 2026_10_18_110000.');
        }

        Storage::fake('workflows_archivos');
        Storage::fake('archivo_expedientes');

        Permission::findOrCreate('Workflows -> Instancias -> Ejecutar', 'web');
        $this->usuario = User::factory()->create(['estado' => 1]);
        $this->usuario->givePermissionTo('Workflows -> Instancias -> Ejecutar');

        $this->workflow = Workflow::create(['nombre' => 'M19-30 archivo', 'estado' => 'activo', 'creador_user_id' => $this->usuario->id]);
        $inicio = $this->workflow->nodos()->create(['tipo' => 'inicio', 'titulo' => 'Inicio', 'posicion_x' => 0, 'posicion_y' => 0]);
        $this->paso = $this->workflow->nodos()->create(['tipo' => 'tarea', 'titulo' => 'Revisar', 'posicion_x' => 0, 'posicion_y' => 0]);
        $fin = $this->workflow->nodos()->create(['tipo' => 'fin', 'titulo' => 'Fin', 'posicion_x' => 0, 'posicion_y' => 0]);
        $this->workflow->conexiones()->create(['nodo_origen_id' => $inicio->id, 'nodo_destino_id' => $this->paso->id]);
        $this->workflow->conexiones()->create(['nodo_origen_id' => $this->paso->id, 'nodo_destino_id' => $fin->id]);

        Schema::disableForeignKeyConstraints();
        $this->radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M1930A-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->usuario->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function expedienteCon(string $estado): OfiArchivoExpediente
    {
        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep W', 'cod_organico' => 'M1930']);
        $serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->usuario->id, 'tipo' => 'Serie', 'cod' => 'W30']);
        Schema::enableForeignKeyConstraints();

        $expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-M1930-W30-'.uniqid(), 'nombre_expediente' => 'Trámite', 'anio' => 2026,
            'dependencia_id' => $depId, 'serie_trd_id' => $serieId, 'estado' => 'abierto',
            'fecha_apertura' => '2026-10-18', 'usuario_apertura_id' => $this->usuario->id,
        ]);
        OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Petición',
            'documentable_type' => VentanillaRadicaReci::class, 'documentable_id' => $this->radicadoId,
            'asunto' => 'Petición', 'fecha_documento' => '2026-10-18', 'usuario_id' => $this->usuario->id,
            'activo' => true, 'orden' => 0,
        ]);
        $expediente->update(['estado' => $estado]);

        return $expediente;
    }

    private function iniciar(): int
    {
        return $this->actingAs($this->usuario)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias", ['origen_tipo' => 'radicado_recibido', 'origen_id' => $this->radicadoId])
            ->assertCreated()->json('data.id');
    }

    private function archivo(string $type, int $id, string $nombre, string $contenido, ?string $hash = null): void
    {
        $ruta = 'workflow/'.$nombre;
        Storage::disk('workflows_archivos')->put($ruta, $contenido);
        WorkFlowArchivo::create([
            'workflow_id' => $this->workflow->id, 'archivable_type' => $type, 'archivable_id' => $id,
            'nombre_original' => $nombre, 'nombre_almacenado' => $nombre, 'ruta_almacenada' => $ruta,
            'mime_type' => 'application/pdf', 'peso_bytes' => strlen($contenido),
            'hash_sha256' => $hash ?? hash('sha256', $contenido), 'disk' => 'workflows_archivos',
            'categoria' => 'evidencia', 'uploaded_by' => $this->usuario->id,
        ]);
    }

    private function completar(int $id): WorkflowInstancia
    {
        Tarea::where('instancia_id', $id)->update(['estado' => 'completada']);
        $this->actingAs($this->usuario)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}/ejecutar", ['nodo_id' => $this->paso->id])
            ->assertOk();

        return WorkflowInstancia::findOrFail($id);
    }

    public function test_incorpora_archivos_y_evidencias_al_expediente_abierto(): void
    {
        $expediente = $this->expedienteCon('abierto');
        $id = $this->iniciar();
        $tarea = Tarea::create(['workflow_id' => $this->workflow->id, 'instancia_id' => $id, 'nodo_id' => $this->paso->id, 'nombre' => 'Revisar soporte', 'estado' => 'pendiente']);
        $this->archivo(WorkflowInstancia::class, $id, 'concepto.pdf', 'concepto técnico');
        $this->archivo(Tarea::class, $tarea->id, 'evidencia.pdf', 'evidencia firmada');

        $instancia = $this->completar($id);

        $this->assertSame('completada', $instancia->estado);
        $this->assertSame('incorporado', $instancia->datos_contexto['expediente']['estado']);
        $this->assertSame($expediente->id, $instancia->datos_contexto['expediente']['expediente_id']);

        $folios = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)->whereNotNull('archivo_path')->orderBy('numero_folio')->get();
        $this->assertSame(['concepto.pdf', 'evidencia.pdf'], $folios->pluck('nombre_original')->all());
        $this->assertSame(['Documento de flujo', 'Evidencia de flujo'], $folios->pluck('tipo_documental')->all());
        $this->assertSame([2, 3], $folios->pluck('numero_folio')->all());
        foreach ($folios as $folio) {
            Storage::disk('archivo_expedientes')->assertExists($folio->archivo_path);
            $this->assertSame($folio->hash_sha256, hash('sha256', Storage::disk('archivo_expedientes')->get($folio->archivo_path)));
            $this->assertSame($this->usuario->id, $folio->usuario_id);
        }
        $this->assertSame(3, $expediente->fresh()->total_folios_elec);
    }

    public function test_sin_expediente_abierto_completa_y_deja_el_aviso(): void
    {
        $cerrado = $this->expedienteCon('cerrado');
        $id = $this->iniciar();
        $this->archivo(WorkflowInstancia::class, $id, 'concepto.pdf', 'concepto técnico');

        $instancia = $this->completar($id);

        $this->assertSame('completada', $instancia->estado);
        $this->assertSame('sin_expediente', $instancia->datos_contexto['expediente']['estado']);
        $this->assertSame(1, $instancia->datos_contexto['expediente']['archivos']);
        $this->assertSame(1, OfiArchivoExpedienteDocumento::where('expediente_id', $cerrado->id)->count());
    }

    public function test_una_huella_alterada_no_folia_nada_y_avisa(): void
    {
        $expediente = $this->expedienteCon('abierto');
        $id = $this->iniciar();
        $this->archivo(WorkflowInstancia::class, $id, 'bueno.pdf', 'contenido bueno');
        $this->archivo(WorkflowInstancia::class, $id, 'alterado.pdf', 'contenido alterado', hash('sha256', 'otro contenido'));

        $instancia = $this->completar($id);

        $this->assertSame('completada', $instancia->estado);
        $this->assertSame('error', $instancia->datos_contexto['expediente']['estado']);
        $this->assertSame(1, OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)->count());
        $this->assertSame([], Storage::disk('archivo_expedientes')->allFiles());
    }

    public function test_sin_origen_no_toca_ningun_expediente(): void
    {
        $id = $this->actingAs($this->usuario)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias")
            ->assertCreated()->json('data.id');

        $instancia = $this->completar($id);

        $this->assertSame('completada', $instancia->estado);
        $this->assertArrayNotHasKey('expediente', $instancia->datos_contexto ?? []);
    }
}
