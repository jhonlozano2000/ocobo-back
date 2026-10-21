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

    private function documento(array $contenido, string $titulo = 'Acta'): int
    {
        $id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => $titulo, 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
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
}
