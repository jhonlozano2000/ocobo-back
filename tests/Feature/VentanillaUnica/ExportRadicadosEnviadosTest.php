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
 * El Excel del listado de Enviados lo armaba el front con el registro crudo (49 columnas: ids, hash,
 * rutas, JSON) y solo con la página visible. Ahora lo genera el back con los filtros del listado,
 * la selección de filas y columnas legibles.
 */
class ExportRadicadosEnviadosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Enviada -> Listar', 'web'));
    }

    private function enviado(string $asunto): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'EXP-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id, 'asunto' => $asunto,
            'hash_sha256' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function csv(string $query): array
    {
        $contenido = $this->actingAs($this->user)->get('/api/ventanilla/radica-enviada/export?format=csv&'.$query)
            ->assertOk()->streamedContent();

        return array_map('str_getcsv', array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF"))));
    }

    public function test_exporta_columnas_legibles_con_los_filtros_del_listado(): void
    {
        $this->enviado('Contrato de obra EXPTEST');
        $this->enviado('Otro asunto');

        $filas = $this->csv('search=EXPTEST');

        $this->assertSame(
            ['Radicado', 'Fecha radicación', 'Asunto', 'Destinatario', 'Clasificación', 'Medio de envío', 'Responsables', 'Fecha documento', 'Fecha vencimiento', 'Estado'],
            $filas[0]
        );
        $this->assertCount(2, $filas);
        $this->assertSame('Contrato de obra EXPTEST', $filas[1][2]);
        $this->assertSame('Pendiente', $filas[1][9]);
        $this->assertStringNotContainsString(str_repeat('a', 64), implode(',', $filas[1]));
    }

    public function test_con_ids_exporta_solo_las_filas_seleccionadas(): void
    {
        $elegido = $this->enviado('Seleccionado EXPSEL');
        $this->enviado('No seleccionado EXPSEL');

        $filas = $this->csv("search=EXPSEL&ids[]={$elegido}");

        $this->assertCount(2, $filas);
        $this->assertSame('Seleccionado EXPSEL', $filas[1][2]);
    }

    public function test_excel_sale_con_el_diseno_de_reportes(): void
    {
        $this->enviado('Excel EXPXLS');

        $respuesta = $this->actingAs($this->user)->get('/api/ventanilla/radica-enviada/export?format=excel&search=EXPXLS')->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $sheet = IOFactory::load($archivo)->getActiveSheet();
        @unlink($archivo);

        $this->assertSame('Radicados Enviados', $sheet->getCell('A2')->getValue());
        $this->assertStringContainsString('Búsqueda: EXPXLS', $sheet->getCell('A4')->getValue());
        $this->assertNotNull($sheet->getFreezePane());
    }
}
