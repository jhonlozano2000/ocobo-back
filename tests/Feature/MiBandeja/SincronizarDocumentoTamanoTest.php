<?php

namespace Tests\Feature\MiBandeja;

use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoController;
use App\Http\Middleware\ValidateRequestSize;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    public function test_sincronizar_detecta_conflicto_de_concurrencia(): void
    {
        $this->withoutMiddleware([ThrottleRequests::class, ValidateRequestSize::class]);
        Event::fake();
        $user = User::factory()->create(['estado' => 1]);
        $id = $this->actingAs($user)->postJson(self::URL, ['titulo' => 'M07-04 LWW'])->assertCreated()->json('data.id');

        // Primera sincronización
        $res1 = $this->postJson(self::URL."/{$id}/sincronizar", [
            'contenido' => ['type' => 'doc', 'content' => [['type' => 'text', 'text' => 'v1']]],
        ])->assertOk();

        $hashV1 = $res1->json('data.hash');

        // Segunda sincronización exitosa pasando hash_base correcto
        $res2 = $this->postJson(self::URL."/{$id}/sincronizar", [
            'contenido' => ['type' => 'doc', 'content' => [['type' => 'text', 'text' => 'v2']]],
            'hash_base' => $hashV1,
        ])->assertOk();

        $hashV2 = $res2->json('data.hash');

        // Tercera sincronización concurrente que quedó desfasada (envía hash_base antiguo)
        $this->postJson(self::URL."/{$id}/sincronizar", [
            'contenido' => ['type' => 'doc', 'content' => [['type' => 'text', 'text' => 'v3 desfasado']]],
            'hash_base' => $hashV1,
        ])->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('errors.hash_servidor', $hashV2);
    }
}
