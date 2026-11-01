<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El export de Recibidos salía con las claves crudas (num_radicado, fec_docu, id), sin los filtros en el
 * membrete y sin poder exportar solo las filas seleccionadas.
 */
class ExportRadicadosRecibidosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(
            Permission::findOrCreate('Radicar -> Cores. Recibida -> Listar', 'web'),
            Permission::findOrCreate('Radicar -> Cores. Recibida -> Ver Todos', 'web'),
        );
    }

    private function recibido(string $asunto): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'REC-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
            'asunto' => $asunto, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function csv(string $query): array
    {
        $contenido = $this->actingAs($this->user)->get('/api/ventanilla/radica-recibida/export?format=csv&'.$query)
            ->assertOk()->streamedContent();

        return array_map('str_getcsv', array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF"))));
    }

    public function test_exporta_columnas_legibles_con_los_filtros_del_listado(): void
    {
        $this->recibido('Derecho de petición RECTEST');
        $this->recibido('Otro asunto');

        $filas = $this->csv('search=RECTEST');

        $this->assertSame(
            ['Radicado', 'Fecha radicación', 'Asunto', 'Remitente', 'Clasificación', 'Medio de recepción', 'Fecha documento', 'Fecha vencimiento', 'Radicado por', 'Estado'],
            $filas[0]
        );
        $this->assertCount(2, $filas);
        $this->assertSame('Derecho de petición RECTEST', $filas[1][2]);
    }

    public function test_con_ids_exporta_solo_las_filas_seleccionadas(): void
    {
        $elegido = $this->recibido('Seleccionado RECSEL');
        $this->recibido('No seleccionado RECSEL');

        $filas = $this->csv("search=RECSEL&ids[]={$elegido}");

        $this->assertCount(2, $filas);
        $this->assertSame('Seleccionado RECSEL', $filas[1][2]);
    }

    public function test_excel_lleva_los_filtros_en_el_membrete(): void
    {
        $this->recibido('Excel RECXLS');

        $respuesta = $this->actingAs($this->user)->get('/api/ventanilla/radica-recibida/export?format=excel&search=RECXLS')->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $sheet = IOFactory::load($archivo)->getActiveSheet();
        @unlink($archivo);

        $this->assertSame('RADICACIONES RECIBIDAS', $sheet->getCell('A3')->getValue());
        $this->assertStringContainsString('Búsqueda: RECXLS', $sheet->getCell('A6')->getValue());
        $this->assertSame('Radicado', $sheet->getCell('A8')->getValue());
    }
}
