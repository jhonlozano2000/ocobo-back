<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use App\Services\MiBandeja\TempDocumentosRecibidos\EstilosDocumento;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Estilos modificados del documento («Modificar estilo» de Word): se guardan con la configuración, solo con lo
 * que cambió, y salen en el PDF y en el .docx.
 */
class EstilosDocumentoTest extends TestCase
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

    private function documento(): int
    {
        $id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Acta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $id,
            'contenido_yjs' => json_encode(['type' => 'doc', 'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Título']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Cuerpo']]],
            ]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_guarda_solo_lo_que_cambio_y_valida(): void
    {
        $id = $this->documento();
        $url = self::URL."/{$id}/configuracion";

        $this->actingAs($this->yo)->patchJson($url, ['configuracion_formato' => ['estilos' => [
            'normal' => ['fuente' => 'Georgia', 'tamano' => 12, 'color' => null],
            'titulo_1' => ['color' => '#1f4e79', 'negrita' => false, 'alineacion' => 'center', 'espacio_antes' => 18],
            'titulo_2' => ['fuente' => ''],
        ]]])->assertOk()
            ->assertJsonPath('data.configuracion.formato.estilos.normal', ['fuente' => 'Georgia', 'tamano' => 12])
            ->assertJsonPath('data.configuracion.formato.estilos.titulo_1.color', '#1f4e79')
            ->assertJsonPath('data.configuracion.formato.estilos.titulo_1.negrita', false)
            ->assertJsonMissingPath('data.configuracion.formato.estilos.titulo_2');

        $this->actingAs($this->yo)->getJson(self::URL."/{$id}")->assertOk()
            ->assertJsonPath('data.configuracion_pagina.formato.estilos.normal.fuente', 'Georgia');

        foreach ([
            ['normal' => ['color' => 'red; background: url(x)']],
            ['normal' => ['fuente' => "Arial'; }"]],
            ['titulo_9' => ['tamano' => 12]],
            ['normal' => ['subrayado' => true]],
            ['normal' => ['tamano' => 300]],
        ] as $estilos) {
            $this->actingAs($this->yo)->patchJson($url, ['configuracion_formato' => ['estilos' => $estilos]])
                ->assertUnprocessable();
        }

        // Sin cambios vuelve a los estilos por defecto
        $this->actingAs($this->yo)->patchJson($url, ['configuracion_formato' => ['estilos' => []]])->assertOk()
            ->assertJsonPath('data.configuracion.formato', null);
    }

    public function test_el_pdf_y_el_docx_llevan_los_estilos(): void
    {
        $estilos = [
            'normal' => ['fuente' => 'Georgia', 'tamano' => 12, 'alineacion' => 'justify'],
            'titulo_1' => ['color' => '#1f4e79', 'tamano' => 20],
        ];
        $css = EstilosDocumento::css($estilos);
        $this->assertStringContainsString("body { font-family: 'Georgia', serif; font-size: 12pt; }", $css);
        $this->assertStringContainsString('p { text-align: justify; }', $css);
        $this->assertStringContainsString('h1 { font-size: 20pt; color: #1f4e79; }', $css);
        $this->assertSame('', EstilosDocumento::css(['normal' => ['fuente' => 'x}; body{display:none']]));

        $id = $this->documento();
        DB::table('mi_bandeja_temp_reci_documentos')->where('id', $id)
            ->update(['configuracion_formato' => json_encode(['estilos' => $estilos])]);

        $respuesta = $this->actingAs($this->yo)->get(self::URL."/{$id}/exportar/docx")->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $estilosXml = $zip->getFromName('word/styles.xml');
        $zip->close();
        @unlink($archivo);

        $this->assertStringContainsString('w:ascii="Georgia"', $estilosXml);
        $this->assertMatchesRegularExpression('/w:styleId="Heading1".*?w:color w:val="1f4e79".*?w:sz w:val="40"/s', $estilosXml);

        $this->actingAs($this->yo)->get(self::URL."/{$id}/exportar/pdf")->assertOk();
    }
}
