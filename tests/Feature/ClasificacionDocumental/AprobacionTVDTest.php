<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M11-03: la TVD no tenía aprobación; cualquiera con permiso la editaba o eliminaba.
 * M11-02: la aprobación es del período del fondo acumulado, no de una dependencia actual.
 */
class AprobacionTVDTest extends TestCase
{
    use DatabaseTransactions;
    use EsquemaFondosAcumulados;

    private User $archivista;

    private User $jefe;

    private int $periodo;

    private int $unidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asegurarEsquema();

        $this->archivista = $this->usuarioTVD();
        $this->jefe = $this->usuarioTVD();
        $this->periodo = $this->periodo($this->fondo());
        $this->unidad = $this->unidad($this->periodo);
    }

    private function aprobar(User $user, ?int $periodo = null)
    {
        return $this->actingAs($user)->postJson('/api/clasifica-documental/tvd/aprobar/'.($periodo ?? $this->periodo));
    }

    public function test_quien_registra_no_aprueba_y_el_jefe_si(): void
    {
        $this->serie($this->periodo, $this->unidad, $this->archivista->id);

        $this->aprobar($this->archivista)->assertStatus(422);
        $this->assertNull(DB::table('fondo_acumulado_periodos')->where('id', $this->periodo)->value('fecha_aprobacion'));

        $this->aprobar($this->jefe)->assertOk()->assertJsonPath('data.elementos', 1);
        $this->assertSame($this->jefe->id, (int) DB::table('fondo_acumulado_periodos')->where('id', $this->periodo)->value('aprobado_por'));
        $this->aprobar($this->jefe)->assertStatus(422);
    }

    public function test_periodo_sin_tvd_o_inexistente_no_se_aprueba(): void
    {
        $this->aprobar($this->jefe)->assertStatus(422);
        $this->aprobar($this->jefe, 999999999)->assertNotFound();
    }

    public function test_tvd_aprobada_no_se_edita_elimina_ni_recibe_elementos(): void
    {
        $serie = $this->serie($this->periodo, $this->unidad, $this->archivista->id);
        // Antes de aprobar se edita
        $this->actingAs($this->archivista)->putJson("/api/clasifica-documental/tvd/{$serie}", ['nom' => 'Editada'])->assertOk();

        $this->aprobar($this->jefe)->assertOk();

        $this->actingAs($this->archivista)->putJson("/api/clasifica-documental/tvd/{$serie}", ['nom' => 'Otra'])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ya fue aprobada'));
        $this->actingAs($this->archivista)->deleteJson("/api/clasifica-documental/tvd/{$serie}")->assertStatus(422);
        $this->actingAs($this->archivista)->postJson('/api/clasifica-documental/tvd', [
            'tipo' => 'SubSerieDocumental', 'cod' => 'S'.random_int(1000, 9999), 'nom' => 'Hija',
            'periodo_id' => $this->periodo, 'unidad_id' => $this->unidad, 'parent' => $serie,
        ])->assertStatus(422);

        $this->assertSame('Editada', DB::table('clasificacion_documental_tvd')->where('id', $serie)->value('nom'));
        $this->assertSame(1, DB::table('clasificacion_documental_tvd')->where('periodo_id', $this->periodo)->count());
    }

    public function test_aprobar_un_periodo_no_bloquea_otro_del_mismo_fondo(): void
    {
        $fondo = (int) DB::table('fondo_acumulado_periodos')->where('id', $this->periodo)->value('fondo_id');
        $otro = $this->periodo($fondo, '1970-01-01', '1989-12-31');
        $unidadOtro = $this->unidad($otro, '200');

        $this->serie($this->periodo, $this->unidad, $this->archivista->id);
        $this->aprobar($this->jefe)->assertOk();

        $this->actingAs($this->archivista)->postJson('/api/clasifica-documental/tvd', [
            'tipo' => 'SerieDocumental', 'cod' => 'N1', 'nom' => 'Serie nueva', 'periodo_id' => $otro, 'unidad_id' => $unidadOtro,
        ])->assertCreated();
    }

    public function test_la_unidad_debe_pertenecer_al_periodo(): void
    {
        $fondo = (int) DB::table('fondo_acumulado_periodos')->where('id', $this->periodo)->value('fondo_id');
        $unidadAjena = $this->unidad($this->periodo($fondo, '1970-01-01', '1989-12-31'), '300');

        $this->actingAs($this->archivista)->postJson('/api/clasifica-documental/tvd', [
            'tipo' => 'SerieDocumental', 'cod' => 'X1', 'nom' => 'Serie', 'periodo_id' => $this->periodo, 'unidad_id' => $unidadAjena,
        ])->assertStatus(422)->assertJsonValidationErrors('unidad_id');
    }
}
