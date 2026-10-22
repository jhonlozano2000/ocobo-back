<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La tabla de contenido del editor (Referencias) sale en las exportaciones: en HTML/PDF y TXT con las entradas
 * tal como quedaron y en .docx como campo TOC de Word.
 */
class TablaContenidoExportTest extends TestCase
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
                ['type' => 'tablaContenido', 'attrs' => ['titulo' => 'Contenido', 'entradas' => [
                    ['nivel' => 1, 'texto' => 'Objeto', 'pagina' => 2],
                    ['nivel' => 2, 'texto' => '<b>Alcance</b>', 'pagina' => 3],
                    'basura',
                ]]],
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Objeto']]],
            ]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Contenido del archivo que descarga la exportación. */
    private function exportado(string $formato): string
    {
        $respuesta = $this->actingAs($this->yo)->get(self::URL."/{$this->id}/exportar/{$formato}")->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $contenido = file_get_contents($archivo);
        @unlink($archivo);

        return $contenido;
    }

    public function test_html_y_txt_llevan_las_entradas(): void
    {
        $html = $this->exportado('html');
        $this->assertStringContainsString('class="tabla-contenido"', $html);
        $this->assertStringContainsString('Objeto</td><td style="padding: 1pt 0 1pt 6pt; text-align: right; width: 30pt;">2', $html);
        $this->assertStringContainsString('&lt;b&gt;Alcance&lt;/b&gt;', $html);

        $this->assertStringContainsString("CONTENIDO\nObjeto ..... 2\n  <b>Alcance</b> ..... 3", $this->exportado('txt'));

        $this->assertStringStartsWith('%PDF', $this->exportado('pdf'));
    }

    public function test_el_docx_lleva_el_campo_toc(): void
    {
        $respuesta = $this->actingAs($this->yo)->get(self::URL."/{$this->id}/exportar/docx")->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $documento = $zip->getFromName('word/document.xml');
        $ajustes = $zip->getFromName('word/settings.xml');
        $zip->close();
        @unlink($archivo);

        $this->assertStringContainsString('TOC \o 1-3', $documento);
        $this->assertStringContainsString('w:updateFields', $ajustes);
    }
}
