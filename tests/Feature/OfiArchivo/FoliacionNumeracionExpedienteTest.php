<?php

namespace Tests\Feature\OfiArchivo;

use App\Helpers\ExpedienteHelper;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M08-07: un folio inactivado se volvía a asignar (choque con uk_expediente_folio).
 * M08-08: el número de expediente contaba sin los borrados lógicos y usaba el año UTC.
 */
class FoliacionNumeracionExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $dependenciaId;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $this->dependenciaId = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Dependencia M08', 'cod_organico' => 'M08D',
        ]);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId([
            'user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'S08',
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(string $numero): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => $numero,
            'nombre_expediente' => 'Expediente '.$numero,
            'anio' => 2026,
            'dependencia_id' => $this->dependenciaId,
            'serie_trd_id' => $this->serieId,
            'estado' => 'abierto',
            'fecha_apertura' => '2026-10-11',
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function documento(OfiArchivoExpediente $expediente): OfiArchivoExpedienteDocumento
    {
        return OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $expediente->id,
            'orden' => 0,
            'tipo_documental' => 'Documento',
            'fecha_documento' => '2026-10-11',
            'asunto' => 'Documento M08-07',
            'usuario_id' => $this->user->id,
        ]);
    }

    public function test_el_folio_de_un_documento_inactivado_no_se_reutiliza(): void
    {
        $expediente = $this->expediente('2026-M08D-S08-9001');
        $this->documento($expediente);
        $this->documento($expediente)->delete();

        $nuevo = $this->documento($expediente);

        $this->assertSame(3, $nuevo->numero_folio);
    }

    public function test_el_numero_no_reutiliza_el_de_un_expediente_borrado(): void
    {
        Carbon::setTestNow('2026-10-11 15:00:00');
        $this->expediente('2026-M08D-S08-0001');
        $this->expediente('2026-M08D-S08-0002')->delete();

        $numero = DB::transaction(fn () => ExpedienteHelper::generarNumeroExpediente($this->dependenciaId, $this->serieId));

        $this->assertSame('2026-M08D-S08-0003', $numero);
    }

    public function test_el_anio_es_el_de_colombia(): void
    {
        // 02:00 UTC del 1-ene = 21:00 del 31-dic en Bogotá
        Carbon::setTestNow('2027-01-01 02:00:00');

        $numero = DB::transaction(fn () => ExpedienteHelper::generarNumeroExpediente($this->dependenciaId, $this->serieId));

        $this->assertStringStartsWith('2026-M08D-S08-', $numero);
    }
}
