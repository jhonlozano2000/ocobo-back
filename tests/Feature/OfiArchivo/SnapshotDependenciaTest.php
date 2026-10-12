<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M09-06: la TRD aprobada y el expediente leían el nombre de la dependencia del organigrama
 * actual; un cambio en el organigrama reescribía la historia.
 */
class SnapshotDependenciaTest extends TestCase
{
    use DatabaseTransactions;

    private int $dep;

    private int $serie;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ofi_archivo_expedientes', 'dependencia_nom')) {
            $this->markTestSkipped('La BD de pruebas no tiene las columnas del snapshot de dependencia');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->dep = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Secretaría Original', 'cod_organico' => 'M0906']);
        $this->serie = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'S0906']);
    }

    private function renombrarDependencia(): void
    {
        DB::table('calidad_organigrama')->where('id', $this->dep)->update(['nom_organico' => 'Secretaría Renombrada', 'cod_organico' => 'M0906X']);
    }

    public function test_el_expediente_conserva_la_dependencia_con_que_se_abrio(): void
    {
        $expediente = OfiArchivoExpediente::create([
            'numero_expediente' => 'M0906-'.uniqid(), 'nombre_expediente' => 'Snapshot', 'anio' => 2026,
            'dependencia_id' => $this->dep, 'serie_trd_id' => $this->serie, 'estado' => 'abierto', 'fecha_apertura' => now(), 'usuario_apertura_id' => $this->user->id,
        ]);

        $this->renombrarDependencia();
        $expediente->update(['nombre_expediente' => 'Otro nombre']);

        $expediente->refresh();
        $this->assertSame('M0906', $expediente->dependencia_cod);
        $this->assertSame('Secretaría Original', $expediente->dependencia_nom);
    }

    public function test_la_trd_guarda_la_dependencia_al_aprobarse(): void
    {
        $version = ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $this->dep, 'version' => 1, 'estado_version' => 'TEMP', 'user_register' => $this->user->id,
        ]);
        $this->assertNull($version->refresh()->dependencia_nom);

        $version->update(['estado_version' => 'ACTIVO']);
        $this->renombrarDependencia();
        $version->update(['observaciones' => 'sin cambio de estado']);

        $version->refresh();
        $this->assertSame('M0906', $version->dependencia_cod);
        $this->assertSame('Secretaría Original', $version->dependencia_nom);
    }
}
