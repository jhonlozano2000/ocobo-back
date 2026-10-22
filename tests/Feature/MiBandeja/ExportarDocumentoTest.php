<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exportación de Mis Documentos a PDF/HTML/TXT desde el back. El editor guarda el JSON de TipTap
 * ({type: doc, content: [...]}) y el exportador lo recorría como una lista de bloques: salía vacío.
 */
class ExportarDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1]);
    }

    private function documento(array $contenido, string $titulo = 'Acta', array $extra = []): int
    {
        $id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => $titulo, 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $id, 'contenido_yjs' => json_encode($contenido),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function exportar(int $id, string $formato): string
    {
        $respuesta = $this->actingAs($this->yo)
            ->get("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$id}/exportar/{$formato}")
            ->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $contenido = file_get_contents($archivo);
        @unlink($archivo);

        return $contenido;
    }

    private static function texto(string $texto, array $marks = []): array
    {
        return array_filter(['type' => 'text', 'text' => $texto, 'marks' => $marks]);
    }

    private static function parrafo(array ...$hijos): array
    {
        return ['type' => 'paragraph', 'content' => $hijos];
    }

    public function test_exporta_el_contenido_guardado_por_el_editor(): void
    {
        $id = $this->documento(['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::texto('Considerandos')]],
            self::parrafo(self::texto('Primero '), self::texto('negrita', [['type' => 'bold']])),
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [self::parrafo(self::texto('Punto uno'))]],
                ['type' => 'listItem', 'content' => [self::parrafo(self::texto('Punto dos'))]],
            ]],
            ['type' => 'table', 'content' => [
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableHeader', 'content' => [self::parrafo(self::texto('Nombre'))]],
                ]],
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableCell', 'content' => [self::parrafo(self::texto('Ana'))]],
                ]],
            ]],
        ]]);

        $html = $this->exportar($id, 'html');
        $this->assertStringContainsString('<h2>Considerandos</h2>', $html);
        $this->assertStringContainsString('<p>Primero <strong>negrita</strong></p>', $html);
        $this->assertStringContainsString('<li><p>Punto uno</p></li>', $html);
        $this->assertStringContainsString('<th><p>Nombre</p></th>', $html);
        $this->assertStringContainsString('<td><p>Ana</p></td>', $html);

        $txt = $this->exportar($id, 'txt');
        $this->assertStringContainsString('CONSIDERANDOS', $txt);
        $this->assertStringContainsString('• Punto uno', $txt);
        $this->assertStringContainsString('Ana', $txt);

        $this->assertStringStartsWith('%PDF', $this->exportar($id, 'pdf'));
    }

    public function test_escapa_el_texto_y_los_enlaces_del_documento(): void
    {
        $id = $this->documento(['type' => 'doc', 'content' => [
            self::parrafo(
                self::texto('<script>alert(1)</script>'),
                self::texto('clic', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]),
                self::texto('web', [['type' => 'link', 'attrs' => ['href' => 'https://ocobo.co/?a=1&b="2"']]]),
            ),
            ['type' => 'image', 'attrs' => ['src' => 'x" onerror="alert(1)', 'alt' => '"><b>']],
        ]], '</title><script>alert(2)</script>');

        $html = $this->exportar($id, 'html');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('<a href="#">clic</a>', $html);
        $this->assertStringContainsString('<a href="https://ocobo.co/?a=1&amp;b=&quot;2&quot;">web</a>', $html);
        $this->assertStringNotContainsString('onerror="', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_exporta_los_formatos_de_la_cinta_tipo_word(): void
    {
        $id = $this->documento(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => [
                self::texto('H'),
                self::texto('2', [['type' => 'subscript']]),
                self::texto('m'),
                self::texto('3', [['type' => 'superscript']]),
                self::texto('Título', [['type' => 'textStyle', 'attrs' => ['fontFamily' => "'Times New Roman', serif", 'fontSize' => '14pt']]]),
            ]],
            ['type' => 'pageBreak'],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'x;color:red'], 'content' => [
                self::texto('raro', [['type' => 'textStyle', 'attrs' => ['fontFamily' => 'a;}body{display:none', 'fontSize' => '1;x', 'color' => 'red;x']]]),
            ]],
        ]]);

        $html = $this->exportar($id, 'html');
        $this->assertStringContainsString('<p style="text-align: center;">H<sub>2</sub>m<sup>3</sup>', $html);
        $this->assertStringContainsString('<span style="font-family: &#039;Times New Roman&#039;, serif; font-size: 14pt;">Título</span>', $html);
        $this->assertStringContainsString('<div style="page-break-after: always;"></div>', $html);
        // Valores que no son CSS válido se descartan en vez de inyectarse en el style
        $this->assertStringContainsString('<p>raro</p>', $html);
    }

    /** Encabezado con el título y pie con «Página n de t» y un texto libre. */
    private function documentoConFranjas(): int
    {
        return $this->documento(['type' => 'doc', 'content' => [
            self::parrafo(self::texto('Hoja uno')),
            ['type' => 'pageBreak'],
            self::parrafo(self::texto('Hoja dos')),
        ]], 'Acta 12', [
            'configuracion_header' => json_encode(['habilitado' => true, 'elementos' => ['titulo']]),
            'configuracion_footer' => json_encode([
                'habilitado' => true, 'elementos' => ['pagina', 'texto_libre'], 'texto_libre' => 'Confidencial {x}',
            ]),
        ]);
    }

    public function test_el_pdf_repite_encabezado_y_pie_en_cada_hoja_con_el_numero_de_pagina(): void
    {
        $id = $this->documentoConFranjas();
        $pdf = $this->exportar($id, 'pdf');

        $archivo = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($archivo, $pdf);
        $texto = shell_exec('pdftotext -enc UTF-8 -layout '.escapeshellarg($archivo).' - 2>&1');
        @unlink($archivo);
        if (! is_string($texto) || ! str_contains($texto, 'Hoja')) {
            $this->markTestSkipped('pdftotext no está disponible para leer el PDF');
        }

        // pdftotext separa las hojas con un salto de página (form feed)
        $hojas = array_values(array_filter(explode(chr(12), $texto), 'trim'));
        $this->assertCount(2, $hojas);
        foreach ($hojas as $i => $hoja) {
            $this->assertStringContainsString('Acta 12', $hoja);
            $this->assertStringContainsString('Página '.($i + 1).' de 2', $hoja);
            $this->assertStringContainsString('Confidencial {x}', $hoja);
        }
    }

    public function test_el_docx_lleva_encabezado_y_pie_con_los_campos_de_pagina_de_word(): void
    {
        $id = $this->documentoConFranjas();
        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($archivo, $this->exportar($id, 'docx'));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $encabezado = $zip->getFromName('word/header1.xml');
        $pie = $zip->getFromName('word/footer1.xml');
        $zip->close();
        @unlink($archivo);

        $this->assertStringContainsString('Acta 12', $encabezado);
        $this->assertStringContainsString('PAGE', $pie);
        $this->assertStringContainsString('NUMPAGES', $pie);
        // Las llaves del texto libre no se convierten en campos de Word
        $this->assertStringContainsString('Confidencial (x)', $pie);
    }

    public function test_exporta_sangria_interlineado_y_espaciado_de_parrafo(): void
    {
        $id = $this->documento(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => [
                'textAlign' => 'justify', 'indent' => 2, 'lineHeight' => '1.5', 'spaceAfter' => 12,
            ], 'content' => [self::texto('Con formato')]],
            // Un valor manipulado no debe colarse en el style
            ['type' => 'paragraph', 'attrs' => ['lineHeight' => '1; color: red', 'indent' => 'x'], 'content' => [
                self::texto('Sin formato'),
            ]],
        ]]);

        $html = $this->exportar($id, 'html');
        $this->assertStringContainsString(
            '<p style="text-align: justify; margin-left: 72pt; line-height: 1.5; margin-bottom: 12px;">Con formato</p>',
            $html
        );
        $this->assertStringContainsString('<p>Sin formato</p>', $html);

        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($archivo, $this->exportar($id, 'docx'));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $documento = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($archivo);

        $this->assertMatchesRegularExpression('/<w:jc w:val="both"\/>/', $documento);
        $this->assertMatchesRegularExpression('/<w:ind w:left="1440"/', $documento);
        $this->assertMatchesRegularExpression('/<w:spacing[^>]*w:after="180"/', $documento);
    }

    public function test_exporta_el_control_de_cambios_con_marcado(): void
    {
        $autor = ['autor' => 1, 'nombre' => 'Ana', 'fecha' => '2026-10-21T10:00'];
        $id = $this->documento(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [
                self::texto('Plazo de '),
                ['type' => 'text', 'text' => 'diez', 'marks' => [['type' => 'eliminacion', 'attrs' => $autor]]],
                ['type' => 'text', 'text' => 'quince', 'marks' => [['type' => 'insercion', 'attrs' => $autor]]],
                self::texto(' días'),
            ]],
        ]]);

        $html = $this->exportar($id, 'html');
        $this->assertStringContainsString('<del style="color: #c62828; text-decoration: line-through;">diez</del>', $html);
        $this->assertStringContainsString('<ins style="color: #2e7d32; text-decoration: underline;">quince</ins>', $html);

        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($archivo, $this->exportar($id, 'docx'));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $documento = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($archivo);

        $this->assertMatchesRegularExpression('/<w:strike w:val="1"\/><\/w:rPr><w:t[^>]*>diez</', $documento);
        $this->assertMatchesRegularExpression('/<w:u w:val="single"\/><\/w:rPr><w:t[^>]*>quince</', $documento);
    }

    public function test_la_configuracion_acepta_el_numero_de_pagina_en_encabezado_y_pie(): void
    {
        $id = $this->documento(['type' => 'doc', 'content' => []]);
        $url = "/api/mi-bandeja/comunicaciones-recibidas/documentos/{$id}/configuracion";

        $this->actingAs($this->yo)->patchJson($url, [
            'configuracion_footer' => ['habilitado' => true, 'elementos' => ['pagina', 'fecha']],
        ])->assertOk()->assertJsonPath('data.configuracion.footer.elementos', ['pagina', 'fecha']);

        $this->actingAs($this->yo)->patchJson($url, [
            'configuracion_header' => ['habilitado' => true, 'elementos' => ['otro']],
        ])->assertStatus(422);
    }
}
