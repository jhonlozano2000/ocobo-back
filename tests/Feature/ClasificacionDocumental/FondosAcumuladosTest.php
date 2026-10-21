<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * M11-02: la TVD se registra por fondo acumulado → período histórico → unidad administrativa del período.
 */
class FondosAcumuladosTest extends TestCase
{
    use DatabaseTransactions;
    use EsquemaFondosAcumulados;

    private const BASE = '/api/clasifica-documental/fondos-acumulados';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asegurarEsquema();
        $this->user = $this->usuarioTVD();
    }

    private function aprobarPeriodo(int $periodo): void
    {
        DB::table('fondo_acumulado_periodos')->where('id', $periodo)->update(['aprobado_por' => $this->user->id, 'fecha_aprobacion' => now()]);
    }

    public function test_crea_fondo_periodos_y_unidades(): void
    {
        $fondo = $this->actingAs($this->user)->postJson(self::BASE, [
            'codigo' => 'FA-'.random_int(1000, 9999), 'nombre' => 'Fondo Instituto', 'entidad_productora' => 'Instituto liquidado',
            'origen' => 'recibido', 'fecha_inicio' => '1960-01-01', 'fecha_fin' => '1995-12-31',
        ])->assertCreated()->json('data.id');

        $periodo = $this->actingAs($this->user)->postJson(self::BASE."/{$fondo}/periodos", [
            'nombre' => 'Estructura 1960', 'fecha_inicio' => '1960-01-01', 'fecha_fin' => '1979-12-31', 'acto_administrativo' => 'Decreto 10 de 1960',
        ])->assertCreated()->json('data.id');

        $raiz = $this->actingAs($this->user)->postJson(self::BASE."/periodos/{$periodo}/unidades", ['codigo' => '100', 'nombre' => 'Dirección'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->user)->postJson(self::BASE."/periodos/{$periodo}/unidades", ['codigo' => '110', 'nombre' => 'Archivo', 'parent' => $raiz])
            ->assertCreated();
        // Código repetido dentro del período
        $this->actingAs($this->user)->postJson(self::BASE."/periodos/{$periodo}/unidades", ['codigo' => '100', 'nombre' => 'Otra'])
            ->assertStatus(422)->assertJsonValidationErrors('codigo');

        $this->actingAs($this->user)->getJson(self::BASE."/periodos/{$periodo}/unidades")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->user)->getJson(self::BASE)->assertOk()
            ->assertJsonFragment(['id' => $periodo, 'unidades_count' => 2, 'aprobado' => false]);
    }

    public function test_periodos_no_se_cruzan_ni_salen_del_fondo(): void
    {
        $fondo = $this->fondo();
        $this->periodo($fondo, '1950-01-01', '1969-12-31');

        $this->actingAs($this->user)->postJson(self::BASE."/{$fondo}/periodos", ['nombre' => 'Cruce', 'fecha_inicio' => '1965-01-01', 'fecha_fin' => '1975-12-31'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
        $this->actingAs($this->user)->postJson(self::BASE."/{$fondo}/periodos", ['nombre' => 'Fuera', 'fecha_inicio' => '1990-01-01', 'fecha_fin' => '2005-12-31'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
        $this->actingAs($this->user)->postJson(self::BASE."/{$fondo}/periodos", ['nombre' => 'Contiguo', 'fecha_inicio' => '1970-01-01', 'fecha_fin' => '1989-12-31'])
            ->assertCreated();

        // Las fechas del fondo no pueden dejar períodos por fuera
        $this->actingAs($this->user)->putJson(self::BASE."/{$fondo}", ['fecha_fin' => '1980-12-31'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_periodo_aprobado_congela_periodo_y_unidades(): void
    {
        $fondo = $this->fondo();
        $periodo = $this->periodo($fondo);
        $unidad = $this->unidad($periodo);
        $this->serie($periodo, $unidad);
        $this->aprobarPeriodo($periodo);

        $this->actingAs($this->user)->putJson(self::BASE."/periodos/{$periodo}", ['nombre' => 'Cambio'])->assertStatus(422);
        $this->actingAs($this->user)->deleteJson(self::BASE."/periodos/{$periodo}")->assertStatus(422);
        $this->actingAs($this->user)->postJson(self::BASE."/periodos/{$periodo}/unidades", ['codigo' => '900', 'nombre' => 'Nueva'])->assertStatus(422);
        $this->actingAs($this->user)->putJson(self::BASE."/unidades/{$unidad}", ['nombre' => 'Cambio'])->assertStatus(422);
        $this->actingAs($this->user)->deleteJson(self::BASE."/unidades/{$unidad}")->assertStatus(422);
        $this->actingAs($this->user)->deleteJson(self::BASE."/{$fondo}")->assertStatus(422);

        $this->assertSame(1, DB::table('fondo_acumulado_unidades')->where('periodo_id', $periodo)->count());
        $this->assertTrue(DB::table('fondos_acumulados')->where('id', $fondo)->exists());
    }

    public function test_unidad_no_depende_de_si_misma_ni_de_otro_periodo(): void
    {
        $fondo = $this->fondo();
        $periodo = $this->periodo($fondo);
        $raiz = $this->unidad($periodo, '100');
        $hija = DB::table('fondo_acumulado_unidades')->insertGetId(['periodo_id' => $periodo, 'codigo' => '110', 'nombre' => 'Hija', 'parent' => $raiz]);
        $ajena = $this->unidad($this->periodo($fondo, '1970-01-01', '1989-12-31'), '500');

        $this->actingAs($this->user)->putJson(self::BASE."/unidades/{$raiz}", ['parent' => $hija])
            ->assertStatus(422)->assertJsonValidationErrors('parent');
        $this->actingAs($this->user)->putJson(self::BASE."/unidades/{$raiz}", ['parent' => $ajena])
            ->assertStatus(422)->assertJsonValidationErrors('parent');
        $this->actingAs($this->user)->deleteJson(self::BASE."/unidades/{$raiz}")->assertStatus(422);
    }

    private function plantilla(string $codigoUnidad): UploadedFile
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setCellValue('A4', 'Unidad');
        $hoja->setCellValueExplicit('B4', $codigoUnidad, DataType::TYPE_STRING);
        // Serie (A, B) con su subserie (A, B, C); E = nombre, H/I = gestión/central
        $hoja->fromArray([
            ['100', 'S1', '', '', 'Actas', '', '', 2, 8, 'Papel', 'CT'],
            ['100', 'S1', 'S1.1', '', 'Actas de junta', '', '', '', '', 'Papel', 'CT'],
        ], null, 'A7');

        $ruta = tempnam(sys_get_temp_dir(), 'tvd').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'tvd.xlsx', null, null, true);
    }

    public function test_la_plantilla_descargable_se_importa_tal_cual(): void
    {
        $periodo = $this->periodo($this->fondo());
        $unidad = $this->unidad($periodo, '010');

        $respuesta = $this->actingAs($this->user)->get('/api/clasifica-documental/tvd/plantilla')->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'tvd').'.xlsx';
        copy($respuesta->baseResponse->getFile()->getPathname(), $ruta);

        // Se llena como lo haría el usuario: código en B4 y filas desde la 7
        $libro = IOFactory::load($ruta);
        $hoja = $libro->getActiveSheet();
        $this->assertSame('Nombre', $hoja->getCell('E6')->getValue());
        $hoja->setCellValue('B4', '010');
        $hoja->fromArray([
            ['010', 'S1', '', '', 'Actas', '', '', 2, 8, 'Papel', 'CT'],
            ['010', 'S1', 'S1.1', '', 'Actas de junta', '', '', '', '', 'Papel', 'CT'],
        ], null, 'A7');
        (new Xlsx($libro))->save($ruta);

        $this->actingAs($this->user)
            ->post('/api/clasifica-documental/tvd/import', ['archivo' => new UploadedFile($ruta, 'tvd.xlsx', null, null, true), 'periodo_id' => $periodo])
            ->assertOk()->assertJsonPath('data.inserted', 2);
        $this->assertSame(2, DB::table('clasificacion_documental_tvd')->where('unidad_id', $unidad)->count());
    }

    public function test_importa_en_la_unidad_del_periodo_elegido(): void
    {
        $periodo = $this->periodo($this->fondo());
        $unidad = $this->unidad($periodo, '100');

        $this->actingAs($this->user)->post('/api/clasifica-documental/tvd/import', ['archivo' => $this->plantilla('100'), 'periodo_id' => $periodo])
            ->assertOk()->assertJsonPath('data.inserted', 2);
        $this->assertSame(2, DB::table('clasificacion_documental_tvd')->where('periodo_id', $periodo)->where('unidad_id', $unidad)->count());

        // Reimportar no duplica la serie ni sus subseries
        $this->actingAs($this->user)->post('/api/clasifica-documental/tvd/import', ['archivo' => $this->plantilla('100'), 'periodo_id' => $periodo])
            ->assertOk()->assertJsonPath('data.inserted', 0);

        // Unidad que no existe en el período
        $this->actingAs($this->user)->post('/api/clasifica-documental/tvd/import', ['archivo' => $this->plantilla('999'), 'periodo_id' => $periodo])
            ->assertOk()->assertJsonPath('data.inserted', 0);

        // Período aprobado: rechaza la importación
        $this->aprobarPeriodo($periodo);
        DB::table('clasificacion_documental_tvd')->where('periodo_id', $periodo)->delete();
        $this->actingAs($this->user)->post('/api/clasifica-documental/tvd/import', ['archivo' => $this->plantilla('100'), 'periodo_id' => $periodo])
            ->assertOk()->assertJsonPath('data.inserted', 0)
            ->assertJsonPath('data.errors.0', fn ($m) => str_contains($m, 'ya fue aprobada'));
        $this->assertSame(0, DB::table('clasificacion_documental_tvd')->where('periodo_id', $periodo)->count());
    }
}
