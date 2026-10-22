<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El contenido del editor se guarda tal cual: los espacios al borde de cada tramo de texto no se recortan.
 */
class EspaciosContenidoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sincronizar_conserva_los_espacios_entre_tramos_con_formato(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $yo = User::factory()->create(['estado' => 1]);
        $url = '/api/mi-bandeja/comunicaciones-recibidas/documentos';
        $id = $this->actingAs($yo)->postJson($url, ['titulo' => '  Informe  '])->assertCreated()->json('data.id');

        $contenido = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Hola '],
            ['type' => 'text', 'text' => 'mundo', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' y adiós '],
        ]]]];
        $this->actingAs($yo)->postJson("{$url}/{$id}/sincronizar", ['contenido' => $contenido])->assertOk();

        $guardado = json_decode(DB::table('mi_bandeja_temp_reci_contenido')->where('documento_id', $id)->value('contenido_yjs'), true);
        $this->assertEquals($contenido, $guardado);
        // Lo demás se sigue recortando
        $this->assertSame('Informe', DB::table('mi_bandeja_temp_reci_documentos')->where('id', $id)->value('titulo'));
    }
}
