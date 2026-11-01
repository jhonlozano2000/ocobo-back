<?php

namespace Tests\Feature\Reportes;

use App\Services\ReportesExportService;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Los reportes exportados salían como una tabla plana: sin membrete de la entidad, sin filtros, sin
 * cabecera fija ni autofiltro en Excel, y el PDF de los módulos que no mandan columnas salía vacío.
 */
class ReportesExportDisenoTest extends TestCase
{
    private const REPORTE = [
        'titulo' => 'Radicaciones Recibidas',
        'total' => 2,
        'columnas' => ['Radicado', 'Asunto', 'Fecha', 'Tercero', 'Estado', 'Responsable', 'Medio'],
        'datos' => [
            ['Radicado' => 'R-1', 'Asunto' => 'Primero', 'Estado' => 'Pendiente'],
            ['Radicado' => 'R-2', 'Asunto' => 'Segundo', 'Estado' => 'Pendiente'],
        ],
        'resumen' => ['Módulo' => 'Radicaciones Recibidas', 'Total' => 2, 'Estado' => 'Pendiente', 'Nota' => 'Acote el rango.'],
    ];

    private function hoja(array $data, array $columnas = []): Worksheet
    {
        $path = app(ReportesExportService::class)->exportarExcel($data, 'test-diseno', $columnas);

        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    private function filaDe(Worksheet $sheet, string $texto): int
    {
        foreach ($sheet->getRowIterator() as $fila) {
            if ($sheet->getCell('A'.$fila->getRowIndex())->getValue() === $texto) {
                return $fila->getRowIndex();
            }
        }
        $this->fail("No se encontró '{$texto}' en la columna A");
    }

    public function test_excel_lleva_membrete_filtros_y_tabla_con_estilo(): void
    {
        $sheet = $this->hoja(self::REPORTE, self::REPORTE['columnas']);
        $cabecera = $this->filaDe($sheet, 'Radicado');

        $this->assertSame('OCOBO — Sistema de Gestión Documental', $sheet->getCell('A1')->getValue());
        $this->assertSame(ReportesExportService::COLOR_OSCURO, $sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('FFFFFF', $sheet->getStyle('A1')->getFont()->getColor()->getRGB());
        $this->assertNotEmpty($sheet->getCell('A2')->getValue());
        $this->assertSame(ReportesExportService::COLOR_PRIMARIO, $sheet->getStyle('A2')->getFont()->getColor()->getRGB());
        $this->assertSame('RADICACIONES RECIBIDAS', $sheet->getCell('A3')->getValue());
        $this->assertSame('E0F2FE', $sheet->getStyle('A3')->getFill()->getStartColor()->getRGB());
        $this->assertStringContainsString('Total registros: 2', $sheet->getCell('A4')->getValue());
        $this->assertSame('Filtros: Módulo: Radicaciones Recibidas   ·   Estado: Pendiente', $sheet->getCell('A5')->getValue());
        $this->assertSame('Acote el rango.', $sheet->getCell('A6')->getValue());
        $this->assertContains('A1:G1', array_keys($sheet->getMergeCells()));

        $estilo = $sheet->getStyle("A{$cabecera}");
        $this->assertSame(ReportesExportService::COLOR_PRIMARIO, $estilo->getFill()->getStartColor()->getRGB());
        $this->assertSame('FFFFFF', $estilo->getFont()->getColor()->getRGB());
        $this->assertTrue($estilo->getFont()->getBold());

        $this->assertSame('R-1', $sheet->getCell('A'.($cabecera + 1))->getValue());
        $this->assertSame('A'.($cabecera + 1), $sheet->getFreezePane());
        $this->assertSame("A{$cabecera}:G".($cabecera + 2), $sheet->getAutoFilter()->getRange());
        $this->assertSame('Total registros:', $sheet->getCell('A'.($cabecera + 3))->getValue());
        $this->assertEquals(2, $sheet->getCell('G'.($cabecera + 3))->getValue());
        $this->assertEquals([$cabecera, $cabecera], $sheet->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $sheet->getPageSetup()->getOrientation());
        $this->assertSame(1, $sheet->getPageSetup()->getFitToWidth());
    }

    public function test_excel_sin_columnas_usa_las_claves_legibles_y_marca_sin_datos(): void
    {
        $sheet = $this->hoja(['titulo' => 'Recibidas', 'datos' => [['num_radicado' => 'R-9', 'fec_docu' => '2026-10-31']]]);
        $cabecera = $this->filaDe($sheet, 'Num radicado');

        $this->assertSame('Fec docu', $sheet->getCell("B{$cabecera}")->getValue());
        $this->assertSame('R-9', $sheet->getCell('A'.($cabecera + 1))->getValue());

        $vacia = $this->hoja(['titulo' => 'Recibidas', 'columnas' => ['Radicado'], 'datos' => []]);
        $this->assertSame('Sin datos', $vacia->getCell('A'.($this->filaDe($vacia, 'Radicado') + 1))->getValue());
    }

    public function test_pdf_recibe_membrete_filtros_y_filas_aunque_el_modulo_no_mande_columnas(): void
    {
        $vista = null;
        View::composer('reportes.export-pdf', function ($view) use (&$vista) {
            $vista = $view->getData();
        });

        $path = app(ReportesExportService::class)->exportarPDF(self::REPORTE, 'test-diseno');
        $sinColumnas = app(ReportesExportService::class)->exportarPDF(['datos' => [['num_radicado' => 'R-9']]], 'test-diseno');
        @unlink($path);
        @unlink($sinColumnas);

        $this->assertSame(['Num radicado'], $vista['columnas']);
        $this->assertSame([['R-9']], $vista['datos']);
        $this->assertNotEmpty($vista['entidad']);
    }

    public function test_pdf_con_filtros_y_nota(): void
    {
        $vista = null;
        View::composer('reportes.export-pdf', function ($view) use (&$vista) {
            $vista = $view->getData();
        });

        $path = app(ReportesExportService::class)->exportarPDF(self::REPORTE, 'test-diseno');
        $contenido = file_get_contents($path);
        @unlink($path);

        $this->assertStringStartsWith('%PDF', $contenido);
        $this->assertLessThan(200 * 1024, strlen($contenido), 'La fuente debe ir con subsetting');
        $this->assertSame(['Módulo' => 'Radicaciones Recibidas', 'Estado' => 'Pendiente'], $vista['resumen']);
        $this->assertSame('Acote el rango.', $vista['nota']);
        $this->assertSame(['R-2', 'Segundo', '', '', 'Pendiente', '', ''], $vista['datos'][1]);
    }
}
