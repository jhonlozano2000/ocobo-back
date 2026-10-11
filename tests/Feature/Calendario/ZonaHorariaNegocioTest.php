<?php

namespace Tests\Feature\Calendario;

use App\Helpers\CalendarioHelper;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\Configuracion\BusinessDaysService;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * M13-01 (opción b): la app sigue en UTC, pero "hoy" y el inicio de los términos se
 * toman en hora de Colombia. Entre las 19:00 y las 24:00 de Bogotá, en UTC ya es mañana.
 */
class ZonaHorariaNegocioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'app.timezone_local' => 'America/Bogota']);

        // Viernes 2026-10-09 21:00 en Bogotá = sábado 2026-10-10 02:00 UTC
        $this->travelTo(Carbon::parse('2026-10-10 02:00:00', 'UTC'));
    }

    public function test_hoy_es_la_fecha_de_colombia(): void
    {
        $this->assertSame('2026-10-10', now()->toDateString());
        $this->assertSame('2026-10-09', CalendarioHelper::hoy()->toDateString());
    }

    public function test_fecha_local_convierte_instantes_y_respeta_fechas(): void
    {
        $this->assertSame('2026-10-09', CalendarioHelper::fechaLocal(now())->toDateString());
        $this->assertSame('2026-10-09', CalendarioHelper::fechaLocal()->toDateString());
        $this->assertSame('2026-10-09', CalendarioHelper::fechaLocal('2026-10-10 02:00:00')->toDateString());
        $this->assertSame('2026-10-12', CalendarioHelper::fechaLocal('2026-10-12')->toDateString());
    }

    public function test_dias_habiles_restantes_cuentan_desde_hoy_en_colombia(): void
    {
        $service = app(BusinessDaysService::class);
        $vence = Carbon::parse('2026-10-15');

        $esperado = $service->diasHabilesEntre(Carbon::parse('2026-10-09'), $vence) - 1;

        $this->assertSame($esperado, $service->diasHabilesRestantes($vence));
    }

    public function test_radicado_que_vence_hoy_en_colombia_no_figura_vencido(): void
    {
        $radicado = new VentanillaRadicaReci(['fec_venci' => '2026-10-09']);

        $this->assertFalse(app(RadicadoEstadoTrabajoService::class)->estaVencido($radicado));

        $radicado->fec_venci = '2026-10-08';
        $this->assertTrue(app(RadicadoEstadoTrabajoService::class)->estaVencido($radicado));
    }
}
