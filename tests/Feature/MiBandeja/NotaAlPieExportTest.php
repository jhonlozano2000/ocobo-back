<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las notas al pie del editor (Referencias) salen numeradas: en HTML/PDF y TXT al final del documento y en .docx
 * como notas al pie de Word.
 */
class NotaAlPieExportTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $yo;

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1]);

        $this->id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Acta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $this->id,
            'contenido_yjs' => json_encode(['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Según el censo'],
                    ['type' => 'notaAlPie', 'attrs' => ['texto' => 'DANE, <2018>.']],
                    ['type' => 'text', 'text' => ' y la encuesta'],
                    ['type' => 'notaAlPie', 'attrs' => ['texto' => 'ECV']],
                ]],
            ]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Ruta del archivo que descarga la exportación. */
    private function archivo(string $formato): string
    {
        return $this->actingAs($this->yo)->get(self::URL."/{$this->id}/exportar/{$formato}")
            ->assertOk()->baseResponse->getFile()->getPathname();
    }

    private function exportado(string $formato): string
    {
        $archivo = $this->archivo($formato);
        $contenido = file_get_contents($archivo);
        @unlink($archivo);

        return $contenido;
    }

    public function test_html_y_txt_numeran_las_notas_al_final(): void
    {
        $html = $this->exportado('html');
        $this->assertStringContainsString('Según el censo<sup>1</sup> y la encuesta<sup>2</sup>', $html);
        $this->assertStringContainsString('<li><sup>1</sup> DANE, &lt;2018&gt;.</li><li><sup>2</sup> ECV</li>', $html);

        $txt = $this->exportado('txt');
        $this->assertStringContainsString('Según el censo[1] y la encuesta[2]', $txt);
        $this->assertStringContainsString("[1] DANE, <2018>.\n[2] ECV", $txt);

        $this->assertStringStartsWith('%PDF', $this->exportado('pdf'));
    }

    public function test_el_docx_lleva_notas_de_word(): void
    {
        $archivo = $this->archivo('docx');
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $documento = $zip->getFromName('word/document.xml');
        $notas = $zip->getFromName('word/footnotes.xml');
        $zip->close();
        @unlink($archivo);

        $this->assertSame(2, substr_count($documento, '<w:footnoteReference'));
        $this->assertStringContainsString('DANE, &lt;2018&gt;.', $notas);
    }
}
