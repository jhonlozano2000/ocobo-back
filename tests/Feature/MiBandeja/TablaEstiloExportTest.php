<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las tablas del editor (pestaña Tabla) salen en HTML/PDF con su estilo, celdas combinadas, sombreado,
 * alineación vertical y anchos de columna.
 */
class TablaEstiloExportTest extends TestCase
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

        $celda = fn (string $texto, array $attrs = [], string $tipo = 'tableCell') => [
            'type' => $tipo, 'attrs' => $attrs,
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $texto]]]],
        ];

        $this->id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Acta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $this->id,
            'contenido_yjs' => json_encode(['type' => 'doc', 'content' => [
                ['type' => 'table', 'attrs' => ['estilo' => 'encabezado'], 'content' => [
                    ['type' => 'tableRow', 'content' => [$celda('Título', ['colspan' => 2, 'colwidth' => [120, 80]], 'tableHeader')]],
                    ['type' => 'tableRow', 'content' => [
                        $celda('Uno', ['backgroundColor' => '#fff2cc', 'verticalAlign' => 'middle', 'rowspan' => 500]),
                        $celda('Dos', ['backgroundColor' => 'red;position:absolute', 'verticalAlign' => 'javascript']),
                    ]],
                ]],
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

    public function test_html_lleva_estilo_combinadas_y_sombreado(): void
    {
        $html = $this->exportado('html');

        $this->assertMatchesRegularExpression('/<th colspan="2" style="[^"]*background-color: #2f5496;[^"]*color: #ffffff;[^"]*width: 200px;/', $html);
        $this->assertMatchesRegularExpression('/<td rowspan="100" style="[^"]*vertical-align: middle; background-color: #fff2cc;/', $html);
        $this->assertStringContainsString('border-left: 1px solid #8eaadb', $html);
        // Valores no válidos no llegan al CSS
        $this->assertStringNotContainsString('position:absolute', $html);
        $this->assertStringNotContainsString('javascript', $html);

        $this->assertStringStartsWith('%PDF', $this->exportado('pdf'));
    }
}
