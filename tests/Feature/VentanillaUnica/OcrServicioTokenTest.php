<?php

namespace Tests\Feature\VentanillaUnica;

use App\Services\VentanillaUnica\OcrHttpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M12-02: el microservicio OCR escuchaba en 0.0.0.0 sin autenticación; Laravel debe enviar
 * el secreto compartido en cada llamada.
 */
class OcrServicioTokenTest extends TestCase
{
    public function test_las_llamadas_al_ocr_llevan_el_secreto_compartido(): void
    {
        config([
            'services.ocr.enabled' => true,
            'services.ocr.url' => 'http://127.0.0.1:5000',
            'services.ocr.token' => 'secreto-de-prueba',
        ]);
        Storage::fake('radicados_recibidos');
        Storage::disk('radicados_recibidos')->put('doc.png', 'contenido');

        Http::fake([
            '*/health' => Http::response(['status' => 'healthy']),
            '*/ocr/extract-text' => Http::response(['success' => true, 'text' => 'hola']),
        ]);

        (new OcrHttpService)->extractText('doc.png');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ocr/extract-text')
            && $r->header('X-OCR-Token') === ['secreto-de-prueba']);
    }
}
