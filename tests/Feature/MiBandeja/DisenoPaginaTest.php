<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Diseño de Word: la marca de agua de texto y el color de página se guardan con la configuración (sin borrar los
 * estilos) y salen en cada hoja del PDF.
 */
class DisenoPaginaTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasColumn('mi_bandeja_temp_reci_documentos', 'configuracion_formato')) {
            $this->markTestSkipped('Falta la migración de configuracion_formato.');
        }
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1]);
    }

    public function test_guarda_marca_de_agua_y_color_y_valida(): void
    {
        $id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Acta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = self::URL."/{$id}/configuracion";

        $this->actingAs($this->yo)->patchJson($url, ['configuracion_formato' => [
            'estilos' => ['normal' => ['fuente' => 'Georgia']],
            'marca_agua' => ['texto' => 'BORRADOR', 'diagonal' => true],
            'color_pagina' => '#fff8e1',
        ]])->assertOk()
            ->assertJsonPath('data.configuracion.formato.marca_agua.texto', 'BORRADOR')
            ->assertJsonPath('data.configuracion.formato.color_pagina', '#fff8e1')
            ->assertJsonPath('data.configuracion.formato.estilos.normal.fuente', 'Georgia');

        // Quitar la marca de agua conserva lo demás
        $this->actingAs($this->yo)->patchJson($url, ['configuracion_formato' => [
            'estilos' => ['normal' => ['fuente' => 'Georgia']],
            'marca_agua' => null,
            'color_pagina' => '#fff8e1',
        ]])->assertOk()
            ->assertJsonMissingPath('data.configuracion.formato.marca_agua')
            ->assertJsonPath('data.configuracion.formato.color_pagina', '#fff8e1');

        foreach ([
            ['color_pagina' => 'red; background: url(x)'],
            ['marca_agua' => ['texto' => str_repeat('A', 61)]],
            ['marca_agua' => ['diagonal' => true]],
            ['marca_agua' => ['texto' => 'X', 'opacidad' => 1]],
        ] as $formato) {
            $this->actingAs($this->yo)->patchJson($url, ['configuracion_formato' => $formato])->assertUnprocessable();
        }
    }

    public function test_el_pdf_lleva_el_color_y_la_marca_en_cada_hoja(): void
    {
        $servicio = new DocumentoExportService;
        $envolver = new \ReflectionMethod($servicio, 'wrapHtmlForPdf');
        $config = [
            'margenes' => ['superior' => 10, 'inferior' => 10, 'izquierdo' => 10, 'derecho' => 10],
            'formato' => ['color_pagina' => '#fff8e1', 'marca_agua' => ['texto' => '<Confidencial>', 'diagonal' => true]],
        ];

        $html = $envolver->invoke($servicio, 'Acta', '<p>Hola</p>', $config, 'Ana');
        $this->assertStringContainsString('background-color: #fff8e1;', $html);
        $this->assertStringContainsString('rotate(-45deg);\'>&lt;Confidencial&gt;</div>', $html);

        $sinDiseno = $envolver->invoke($servicio, 'Acta', '<p>Hola</p>', ['formato' => null], 'Ana');
        // Solo la regla CSS del encabezado y pie: ninguna capa de diseño
        $this->assertSame(1, substr_count($sinDiseno, 'position: fixed'));
    }
}
