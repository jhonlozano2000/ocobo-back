<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comentarios de Mis Documentos anclados al texto, como en Word: se guarda qué texto se comentó y dónde, y se
 * pueden resolver o eliminar (antes esas rutas quedaban con «comentarios» repetido y respondían 404).
 */
class ComentariosDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/api/mi-bandeja/comunicaciones-recibidas';

    private User $yo;

    private int $documento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1]);
        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Acta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function comentar(array $datos)
    {
        return $this->actingAs($this->yo)->postJson(self::BASE."/documentos/{$this->documento}/comentarios", $datos);
    }

    public function test_guarda_el_texto_comentado_con_su_posicion(): void
    {
        $id = $this->comentar([
            'contenido' => 'Revisar la fecha',
            'seleccion_texto' => ['from' => 4, 'to' => 15, 'text' => 'del 3 de mayo'],
        ])->assertCreated()->json('data.comentario.id');

        $this->comentar(['contenido' => 'De acuerdo', 'parent_id' => $id])->assertCreated();

        $lista = $this->actingAs($this->yo)->getJson(self::BASE."/documentos/{$this->documento}/comentarios")
            ->assertOk()
            ->json('data.comentarios');

        $this->assertCount(1, $lista);
        $this->assertEquals(['from' => 4, 'to' => 15, 'text' => 'del 3 de mayo'], $lista[0]['seleccion_texto']);
        $this->assertSame('De acuerdo', $lista[0]['respuestas'][0]['contenido']);
    }

    public function test_rechaza_una_seleccion_sin_posiciones_validas(): void
    {
        $this->comentar(['contenido' => 'x', 'seleccion_texto' => ['text' => 'hola']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seleccion_texto.from', 'seleccion_texto.to']);

        $this->comentar(['contenido' => 'x', 'seleccion_texto' => ['from' => 9, 'to' => 2]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seleccion_texto.to']);
    }

    public function test_resolver_reabrir_y_eliminar_un_comentario(): void
    {
        $id = $this->comentar(['contenido' => 'Cambiar el título'])->json('data.comentario.id');

        $this->actingAs($this->yo)->postJson(self::BASE."/comentarios/{$id}/resolver")->assertOk();
        $this->assertTrue((bool) DB::table('mi_bandeja_temp_reci_comentarios')->where('id', $id)->value('resuelto'));

        $this->actingAs($this->yo)->postJson(self::BASE."/comentarios/{$id}/desresolver")->assertOk();
        $this->assertFalse((bool) DB::table('mi_bandeja_temp_reci_comentarios')->where('id', $id)->value('resuelto'));

        $this->actingAs($this->yo)->deleteJson(self::BASE."/comentarios/{$id}")->assertOk();
        $this->assertDatabaseMissing('mi_bandeja_temp_reci_comentarios', ['id' => $id]);
    }

    public function test_quien_no_tiene_acceso_no_ve_los_comentarios(): void
    {
        $this->comentar(['contenido' => 'Privado'])->assertCreated();
        $otro = User::factory()->create(['estado' => 1]);

        $this->actingAs($otro)->getJson(self::BASE."/documentos/{$this->documento}/comentarios")->assertForbidden();
    }
}
