<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion\ConfigCalendarioFestivo;
use App\Models\User;
use App\Services\Configuracion\BusinessDaysService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M13-02 / M13-03: festivos nacionales con Ley Emiliani y corrección de fechas guardadas.
 */
class FestivosColombiaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_festivos_2026_aplican_ley_emiliani(): void
    {
        $festivos = app(BusinessDaysService::class)->getFestivosNacionalesColombia(2026);
        $fechas = array_column($festivos, 'fecha');

        $this->assertCount(18, $festivos);
        $this->assertSame([
            '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03',
            '2026-05-01', '2026-05-18', '2026-06-08', '2026-06-15', '2026-06-29',
            '2026-07-20', '2026-08-07', '2026-08-17', '2026-10-12', '2026-11-02',
            '2026-11-16', '2026-12-08', '2026-12-25',
        ], $fechas);
    }

    public function test_festivo_que_cae_lunes_no_se_traslada(): void
    {
        // 2027-03-19 es viernes → 22; 2027-11-01 es lunes → se queda
        $fechas = array_column(app(BusinessDaysService::class)->getFestivosNacionalesColombia(2027), 'fecha');

        $this->assertContains('2027-03-22', $fechas);
        $this->assertContains('2027-11-01', $fechas);
    }

    public function test_regenerar_corrige_fechas_erradas_y_respeta_otros_tipos(): void
    {
        $this->actingAs(User::factory()->create(['estado' => 1]));
        ConfigCalendarioFestivo::where('anio', 2026)->delete();
        // Fecha que generaba el código anterior (Pascua + 39, jueves)
        ConfigCalendarioFestivo::create(['fecha' => '2026-05-14', 'nombre' => 'Ascensión del Señor', 'tipo' => 'Nacional', 'anio' => 2026]);
        ConfigCalendarioFestivo::create(['fecha' => '2026-09-10', 'nombre' => 'Aniversario', 'tipo' => 'Empresarial', 'anio' => 2026]);

        $r = app(BusinessDaysService::class)->generarFestivosColombia(2026);

        $this->assertSame(1, $r['corregidos']);
        $this->assertSame(18, $r['creados']);
        $this->assertDatabaseMissing('config_calendario_festivos', ['fecha' => '2026-05-14']);
        $this->assertDatabaseHas('config_calendario_festivos', ['fecha' => '2026-05-18', 'nombre' => 'Ascensión del Señor']);
        $this->assertDatabaseHas('config_calendario_festivos', ['fecha' => '2026-09-10', 'tipo' => 'Empresarial']);
    }

    public function test_comando_exige_usuario_y_sincroniza(): void
    {
        $this->artisan('festivos:sincronizar-colombia', ['anio' => [2026]])->assertFailed();

        $usuario = User::factory()->create(['estado' => 1]);
        $this->artisan('festivos:sincronizar-colombia', ['anio' => [2026], '--usuario' => $usuario->id])->assertSuccessful();

        $this->assertDatabaseHas('config_calendario_festivos', ['fecha' => '2026-06-15', 'nombre' => 'Sagrado Corazón']);
    }
}
