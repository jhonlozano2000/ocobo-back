<?php

namespace Tests\Feature\MiBandeja;

use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoController;
use App\Http\Middleware\ValidateRequestSize;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * M07-03: `sincronizar` guardaba cualquier `contenido` sin validar tipo ni tamaño, y
 * ValidateRequestSize solo miraba el header Content-Length (ausente en peticiones chunked).
 */
class SincronizarDocumentoTamanoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    public function test_sincronizar_valida_tipo_y_tamano_del_contenido(): void
    {
        $this->withoutMiddleware([ThrottleRequests::class, ValidateRequestSize::class]);
        Event::fake();
        $user = User::factory()->create(['estado' => 1]);
        $id = $this->actingAs($user)->postJson(self::URL, ['titulo' => 'M07-03'])->assertCreated()->json('data.id');

        $this->postJson(self::URL."/{$id}/sincronizar", ['contenido' => 'texto'])
            ->assertUnprocessable()->assertJsonValidationErrors('contenido');

        $enorme = ['type' => 'doc', 'content' => [['type' => 'text', 'text' => str_repeat('a', DocumentoController::MAX_CONTENIDO_BYTES)]]];
        $this->postJson(self::URL."/{$id}/sincronizar", ['contenido' => $enorme])
            ->assertUnprocessable()->assertJsonValidationErrors('contenido');

        $this->postJson(self::URL."/{$id}/sincronizar", ['contenido' => ['type' => 'doc', 'content' => []]])->assertOk();
    }

    public function test_el_limite_de_tamano_aplica_sin_content_length(): void
    {
        config(['security.max_request_size' => 100]);
        $request = Request::create('/api/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 101));
        $request->headers->remove('Content-Length');

        $response = (new ValidateRequestSize)->handle($request, fn () => response('ok'));

        $this->assertSame(413, $response->getStatusCode());
    }
}
