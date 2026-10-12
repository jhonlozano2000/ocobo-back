<?php

namespace Tests\Feature\VentanillaUnica;

use App\Jobs\AplicarOcrArchivoDigital;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Services\VentanillaUnica\OcrHttpService;
use App\Services\VentanillaUnica\OcrService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M12-03: el OCR se ejecutaba dentro del request de subida (hasta 60 s) y hacía un
 * health-check HTTP antes de cada extracción.
 */
class OcrSegundoPlanoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ocr.enabled' => true,
            'services.ocr.url' => 'http://127.0.0.1:5000',
            'services.ocr.token' => 'secreto-de-prueba',
        ]);
        Storage::fake('radicados_internos');
        Http::fake([
            '*/health' => Http::response(['status' => 'healthy']),
            '*/ocr/extract-text' => Http::response(['success' => true, 'text' => 'texto extraído']),
        ]);
    }

    private function interno(string $ruta): VentanillaRadicaInterno
    {
        Storage::disk('radicados_internos')->put($ruta, 'contenido');
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'M1203-'.uniqid(), 'clasifica_documen_id' => 1, 'usuario_crea' => 1, 'archivo_digital' => $ruta,
        ]);
        Schema::enableForeignKeyConstraints();

        return VentanillaRadicaInterno::withoutGlobalScopes()->findOrFail($id);
    }

    public function test_el_job_guarda_el_texto_del_archivo_vigente(): void
    {
        $radicado = $this->interno('2026/doc.pdf');

        (new AplicarOcrArchivoDigital($radicado::class, $radicado->id, 'radicados_internos', '2026/doc.pdf'))
            ->handle(app(OcrHttpService::class), app(OcrService::class));

        $radicado->refresh();
        $this->assertSame('texto extraído', $radicado->ocr);
        $this->assertTrue((bool) $radicado->ocr_aplicado);
    }

    public function test_si_el_archivo_se_reemplazo_no_aplica_el_ocr_viejo(): void
    {
        $radicado = $this->interno('2026/nuevo.pdf');

        (new AplicarOcrArchivoDigital($radicado::class, $radicado->id, 'radicados_internos', '2026/anterior.pdf'))
            ->handle(app(OcrHttpService::class), app(OcrService::class));

        $this->assertNull($radicado->refresh()->ocr);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/ocr/extract-text'));
    }

    public function test_programar_no_ejecuta_el_ocr_en_el_request(): void
    {
        Bus::fake();
        $radicado = $this->interno('2026/doc.pdf');

        AplicarOcrArchivoDigital::programar($radicado, 'radicados_internos', '2026/doc.pdf');

        Bus::assertDispatchedAfterResponse(AplicarOcrArchivoDigital::class);
        Http::assertNothingSent();
    }

    public function test_el_health_check_se_cachea(): void
    {
        $servicio = new OcrHttpService;
        Storage::disk('radicados_internos')->put('a.pdf', 'x');

        $servicio->extractText('a.pdf', 'radicados_internos');
        $servicio->extractText('a.pdf', 'radicados_internos');

        $this->assertCount(1, collect(Http::recorded())->filter(fn ($par) => str_ends_with($par[0]->url(), '/health')));
    }
}
