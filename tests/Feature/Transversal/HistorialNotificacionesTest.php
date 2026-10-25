<?php

namespace Tests\Feature\Transversal;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 16.18: contrato que consume la página de historial: { data: [...], pagination: { total } }, solo
 * las notificaciones propias, y el contador de no leídas independiente de la página.
 */
class HistorialNotificacionesTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTA = '/api/transversal/in-app-notifications';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
    }

    private function notificacion(User $user, string $titulo, bool $leida = false, ?string $fecha = null): int
    {
        return DB::table('notifications')->insertGetId([
            'user_id' => $user->id, 'type' => 'sistema', 'title' => $titulo, 'message' => 'Mensaje',
            'data' => json_encode(['url' => '/mi-bandeja']), 'read_at' => $leida ? now() : null,
            'created_at' => $fecha ?? now(), 'updated_at' => now(),
        ]);
    }

    public function test_lista_paginada_solo_con_las_propias_y_de_la_mas_reciente(): void
    {
        $vieja = $this->notificacion($this->user, 'Vieja', true, '2026-10-01 08:00:00');
        $nueva = $this->notificacion($this->user, 'Nueva', false, '2026-10-20 08:00:00');
        $media = $this->notificacion($this->user, 'Media', false, '2026-10-10 08:00:00');
        $this->notificacion(User::factory()->create(), 'Ajena');

        $respuesta = $this->actingAs($this->user)->getJson(self::RUTA.'?page=1&per_page=2')->assertOk();

        $this->assertSame([$nueva, $media], array_column($respuesta->json('data'), 'id'));
        $respuesta->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('data.0.data.url', '/mi-bandeja');
        $this->assertStringEndsWith('Z', $respuesta->json('data.0.created_at'));

        $this->assertSame([$vieja], array_column(
            $this->actingAs($this->user)->getJson(self::RUTA.'?page=2&per_page=2')->json('data'), 'id'
        ));
        $this->actingAs($this->user)->getJson(self::RUTA.'/unread-count')->assertOk()->assertJsonPath('data.count', 2);
    }

    public function test_marcar_una_y_todas_solo_afecta_las_propias(): void
    {
        $una = $this->notificacion($this->user, 'Una');
        $this->notificacion($this->user, 'Otra');
        $ajena = $this->notificacion(User::factory()->create(), 'Ajena');

        $this->actingAs($this->user)->patchJson(self::RUTA."/{$ajena}/read")->assertNotFound();
        $this->actingAs($this->user)->patchJson(self::RUTA."/{$una}/read")->assertOk();
        $this->actingAs($this->user)->getJson(self::RUTA.'/unread-count')->assertJsonPath('data.count', 1);

        $this->actingAs($this->user)->postJson(self::RUTA.'/read-all')->assertOk()->assertJsonPath('data.marked', 1);
        $this->actingAs($this->user)->getJson(self::RUTA.'/unread-count')->assertJsonPath('data.count', 0);
        $this->assertNull(DB::table('notifications')->where('id', $ajena)->value('read_at'));
    }
}
