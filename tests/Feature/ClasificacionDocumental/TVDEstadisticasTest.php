<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 5.2: las tarjetas de la TVD leen /tvd/estadisticas. Se comparan incrementos sobre lo que ya
 * exista en la base para no depender de los datos sembrados.
 */
class TVDEstadisticasTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTA = '/api/clasifica-documental/tvd/estadisticas';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('TVD -> Listar', 'web');
        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo('TVD -> Listar');
    }

    private function estadisticas(): array
    {
        return $this->actingAs($this->user)->getJson(self::RUTA)->assertOk()->json('data');
    }

    public function test_las_estadisticas_cuentan_series_subseries_periodos_y_unidades(): void
    {
        $antes = $this->estadisticas();
        $this->assertEqualsCanonicalizing(
            ['total_elementos', 'total_series', 'total_subseries', 'total_fondos', 'total_periodos', 'periodos_aprobados', 'total_unidades'],
            array_keys($antes)
        );

        $fondo = DB::table('fondos_acumulados')->insertGetId([
            'codigo' => 'F-'.uniqid(), 'nombre' => 'Fondo estadísticas', 'entidad_productora' => 'Entidad', 'created_at' => now(),
        ]);
        $periodo = fn (?string $aprobado) => DB::table('fondo_acumulado_periodos')->insertGetId([
            'fondo_id' => $fondo, 'nombre' => 'Período', 'fecha_inicio' => '1980-01-01', 'fecha_fin' => '1990-12-31',
            'fecha_aprobacion' => $aprobado, 'created_at' => now(),
        ]);
        $abierto = $periodo(null);
        $periodo('2026-10-01');
        $unidad = fn () => DB::table('fondo_acumulado_unidades')->insertGetId([
            'periodo_id' => $abierto, 'codigo' => 'U-'.uniqid(), 'nombre' => 'Unidad', 'created_at' => now(),
        ]);
        $u1 = $unidad();
        $u2 = $unidad();

        $elemento = fn (string $tipo, int $unidadId) => DB::table('clasificacion_documental_tvd')->insertGetId([
            'periodo_id' => $abierto, 'unidad_id' => $unidadId, 'tipo' => $tipo, 'cod' => uniqid(), 'nom' => $tipo, 'created_at' => now(),
        ]);
        $elemento('SerieDocumental', $u1);
        $elemento('SerieDocumental', $u2);
        $elemento('SubSerieDocumental', $u2);

        $despues = $this->estadisticas();

        $this->assertSame(3, $despues['total_elementos'] - $antes['total_elementos']);
        $this->assertSame(2, $despues['total_series'] - $antes['total_series']);
        $this->assertSame(1, $despues['total_subseries'] - $antes['total_subseries']);
        $this->assertSame(1, $despues['total_fondos'] - $antes['total_fondos']);
        $this->assertSame(2, $despues['total_periodos'] - $antes['total_periodos']);
        $this->assertSame(1, $despues['periodos_aprobados'] - $antes['periodos_aprobados']);
        // Unidades distintas con elementos, no filas
        $this->assertSame(2, $despues['total_unidades'] - $antes['total_unidades']);
    }

    public function test_sin_permiso_de_listar_no_las_ve(): void
    {
        $this->actingAs(User::factory()->create(['estado' => 1]))->getJson(self::RUTA)->assertForbidden();
    }
}
