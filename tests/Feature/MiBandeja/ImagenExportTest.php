<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las imágenes del editor (pestaña Imagen) salen en HTML/PDF con su tamaño, ajuste de texto, alineación y título.
 */
class ImagenExportTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAACCAYAAAB/qH1jAAAAEUlEQVR42mP8z8DwnwEJMBIWAAA0xgP9UF6tBQAAAABJRU5ErkJggg==';

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
                ['type' => 'image', 'attrs' => [
                    'src' => self::PNG, 'width' => 200, 'ajuste' => 'cuadrado_derecha', 'alt' => 'Escudo',
                    'titulo' => 'Figura 1: <Escudo>',
                ]],
                ['type' => 'image', 'attrs' => ['src' => self::PNG, 'alineacion' => 'centro', 'width' => '9999']],
                ['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']],
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

    public function test_html_lleva_tamano_ajuste_y_titulo(): void
    {
        $html = $this->exportado('html');

        $this->assertStringContainsString('<div style="float: right; margin: 0.25em 0 0.5em 1em; width: 200px; max-width: 100%;"><img src="'.self::PNG.'" alt="Escudo"', $html);
        $this->assertStringContainsString('Figura 1: &lt;Escudo&gt;</div>', $html);
        $this->assertStringContainsString('margin: 0.5em auto; text-align: center; width: 2000px;', $html);
        $this->assertStringNotContainsString('javascript', $html);

        $this->assertStringStartsWith('%PDF', $this->exportado('pdf'));
    }
}
