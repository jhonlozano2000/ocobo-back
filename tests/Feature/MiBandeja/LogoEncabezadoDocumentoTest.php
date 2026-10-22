<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Logo de la entidad en el encabezado o pie de los documentos de Mis Documentos: se entrega al editor por su
 * propio endpoint (está en un disco privado) y se repite en cada hoja del PDF y del .docx.
 */
class LogoEncabezadoDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas';

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1]);

        Storage::fake('otros_archivos');
        Storage::disk('otros_archivos')->put('logo.png', UploadedFile::fake()->image('logo.png', 200, 100)->get());
        DB::table('config_varias')->updateOrInsert(['clave' => 'logo_empresa'], ['valor' => 'logo.png']);
    }

    private function documento(array $header): int
    {
        $id = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Oficio', 'estado' => 'borrador',
            'configuracion_header' => json_encode($header),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mi_bandeja_temp_reci_contenido')->insert([
            'documento_id' => $id,
            'contenido_yjs' => json_encode(['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Cuerpo']]],
            ]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function exportar(int $id, string $formato): string
    {
        $respuesta = $this->actingAs($this->yo)->get(self::URL."/documentos/{$id}/exportar/{$formato}")->assertOk();
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $contenido = file_get_contents($archivo);
        @unlink($archivo);

        return $contenido;
    }

    public function test_entrega_el_logo_solo_si_es_una_imagen(): void
    {
        $this->getJson(self::URL.'/logo-entidad')->assertUnauthorized();

        $respuesta = $this->actingAs($this->yo)->get(self::URL.'/logo-entidad')->assertOk();
        $this->assertSame('image/png', $respuesta->headers->get('Content-Type'));
        $this->assertSame(Storage::disk('otros_archivos')->get('logo.png'), $respuesta->getContent());

        Storage::disk('otros_archivos')->put('logo.png', '<svg onload="alert(1)"></svg>');
        $this->actingAs($this->yo)->get(self::URL.'/logo-entidad')->assertNoContent();
    }

    public function test_el_encabezado_acepta_el_logo(): void
    {
        $id = $this->documento(['habilitado' => false]);

        $this->actingAs($this->yo)->patchJson(self::URL."/documentos/{$id}/configuracion", [
            'configuracion_header' => ['habilitado' => true, 'elementos' => ['logo', 'titulo']],
        ])->assertOk()->assertJsonPath('data.configuracion.header.elementos', ['logo', 'titulo']);

        $this->actingAs($this->yo)->patchJson(self::URL."/documentos/{$id}/configuracion", [
            'configuracion_header' => ['habilitado' => true, 'elementos' => ['escudo']],
        ])->assertUnprocessable();
    }

    public function test_el_docx_y_el_pdf_llevan_el_logo_en_el_encabezado(): void
    {
        $id = $this->documento(['habilitado' => true, 'elementos' => ['logo', 'titulo']]);

        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($archivo, $this->exportar($id, 'docx'));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $encabezado = $zip->getFromName('word/header1.xml');
        $relaciones = $zip->getFromName('word/_rels/header1.xml.rels');
        $zip->close();
        @unlink($archivo);

        $this->assertStringContainsString('Oficio', $encabezado);
        // 2:1 con el 70 % del margen por defecto (25,4 mm = 72 pt)
        $this->assertMatchesRegularExpression('/width:100\.8pt;\s*height:50\.4pt/', $encabezado);
        $this->assertStringContainsString('media/', $relaciones);

        $pdf = $this->exportar($id, 'pdf');
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('#/Subtype\s*/Image#', $pdf);
    }

    public function test_sin_el_elemento_logo_el_pdf_no_lleva_imagen(): void
    {
        $id = $this->documento(['habilitado' => true, 'elementos' => ['titulo']]);

        $this->assertDoesNotMatchRegularExpression('#/Subtype\s*/Image#', $this->exportar($id, 'pdf'));
    }
}
