<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B13 (diseño de expedientes §3.5): un expediente cerrado se reabre con permiso propio y motivo;
 * conserva las versiones firmadas del índice y recalcula su retención en el siguiente cierre.
 */
class ReaperturaExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ofi_archivo_expedientes', 'motivo_reapertura')
            || ! Schema::hasColumn('ofi_archivo_expedientes', 'trd_snapshot')
            || ! Schema::hasTable('ofi_archivo_expediente_indice_versiones')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_19_130000, 140000 y 160000.');
        }

        $this->user = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Archivista']);
        foreach (['Ver Todos', 'Ver', 'Cerrar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B13', 'cod_organico' => 'B13D']);
        $serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'S13', 'nom' => 'Contratos', 'dependencia_id' => $depId, 'user_register' => $this->user->id,
            'a_g' => 2, 'a_c' => 3,
        ]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B13D-S13-'.uniqid(), 'nombre_expediente' => 'Contrato 13', 'anio' => 2026,
            'dependencia_id' => $depId, 'serie_trd_id' => $serie, 'estado' => 'abierto', 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id,
        ]);
        $this->folio('Acta de inicio');
    }

    private function folio(string $asunto): OfiArchivoExpedienteDocumento
    {
        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $this->expediente->id, 'tipo' => 'tipo_documental', 'tipo_documental' => 'Acta', 'tipo_documental_id' => 1,
            'asunto' => $asunto, 'fecha_documento' => '2026-10-19', 'hash_sha256' => hash('sha256', $asunto),
            'usuario_id' => $this->user->id, 'activo' => true, 'orden' => 0,
        ]);
    }

    private function reabrir(array $datos = ['motivo_reapertura' => 'Llegó un otrosí al contrato'])
    {
        return $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/reabrir", $datos);
    }

    public function test_reabrir_exige_su_propio_permiso_motivo_y_un_expediente_cerrado(): void
    {
        $this->reabrir()->assertForbidden();

        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Reabrir', 'web'));
        $this->reabrir()->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'Solo se pueden reabrir expedientes cerrados'));

        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", ['motivo_cierre' => 'Fin'])->assertOk();
        $this->reabrir(['motivo_reapertura' => ''])->assertUnprocessable()->assertJsonValidationErrors('motivo_reapertura');

        $this->expediente->update(['estado' => 'transferido']);
        $this->reabrir()->assertUnprocessable();
        $this->assertSame('transferido', $this->expediente->fresh()->estado);
    }

    public function test_reabrir_limpia_el_cierre_conserva_el_indice_firmado_y_el_nuevo_cierre_genera_otra_version(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Reabrir', 'web'));
        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", ['motivo_cierre' => 'Fin', 'fecha_cierre' => '2026-10-15'])->assertOk();
        $cerrado = $this->expediente->fresh();
        $this->assertSame('2028-10-15', $cerrado->fecha_fin_gestion->toDateString());

        $this->reabrir()->assertOk()->assertJsonPath('data.estado', 'abierto');

        $reabierto = $this->expediente->fresh();
        $this->assertSame('Llegó un otrosí al contrato', $reabierto->motivo_reapertura);
        foreach (['fecha_cierre', 'usuario_cierre_id', 'motivo_cierre', 'hash_indice', 'trd_snapshot', 'fecha_fin_gestion', 'fecha_fin_central'] as $campo) {
            $this->assertNull($reabierto->{$campo}, "{$campo} se limpia al reabrir");
        }
        $this->assertSame(1, OfiArchivoExpedienteIndiceVersion::where('expediente_id', $this->expediente->id)->count());

        $evento = OfiArchivoExpedienteEvento::where('expediente_id', $this->expediente->id)->orderByDesc('secuencia')->first();
        $this->assertSame('reapertura', $evento->accion);
        $this->assertSame('Llegó un otrosí al contrato', $evento->detalle['cambios']['motivo_reapertura']['despues']);
        $this->assertSame('Fin', $evento->detalle['cambios']['motivo_cierre']['antes']);

        // Reabierto admite folios y el nuevo cierre recalcula la retención y firma la versión 2
        $this->folio('Otrosí');
        $this->postJson("/api/archivo/expedientes/{$this->expediente->id}/cerrar", ['motivo_cierre' => 'Fin del otrosí'])->assertOk();
        $this->assertSame([1, 2], OfiArchivoExpedienteIndiceVersion::where('expediente_id', $this->expediente->id)->orderBy('version')->pluck('version')->all());
        $this->assertSame(2, OfiArchivoExpedienteIndiceVersion::where('expediente_id', $this->expediente->id)->where('version', 2)->value('total_folios'));
        $this->assertSame(now()->addYears(2)->toDateString(), $this->expediente->fresh()->fecha_fin_gestion->toDateString());
    }
}
