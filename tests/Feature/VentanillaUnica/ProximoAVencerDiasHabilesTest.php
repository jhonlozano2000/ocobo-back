<?php

namespace Tests\Feature\VentanillaUnica;

use App\Helpers\CalendarioHelper;
use App\Models\Configuracion\ConfigVarias;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M04-09: "próximos a vencer" sumaba 5 días calendario; si había fin de semana de por medio,
 * la ventana real era de 3 días hábiles.
 */
class ProximoAVencerDiasHabilesTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_ventana_es_de_cinco_dias_habiles(): void
    {
        ConfigVarias::setConsiderarDiasHabiles(true);
        Carbon::setTestNow('2026-10-22 09:00:00'); // jueves, sin festivos hasta el 29

        $this->assertSame('2026-10-29', CalendarioHelper::limiteProximoAVencer()->toDateString());
    }

    public function test_respeta_la_configuracion_de_dias_calendario(): void
    {
        ConfigVarias::setConsiderarDiasHabiles(false);
        Carbon::setTestNow('2026-10-22 09:00:00');

        $this->assertSame('2026-10-27', CalendarioHelper::limiteProximoAVencer()->toDateString());
    }
}
