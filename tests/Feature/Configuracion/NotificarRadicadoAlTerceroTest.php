<?php

namespace Tests\Feature\Configuracion;

use App\Helpers\AcuseReciboHelper;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El switch "Enviar notificación con archivos adjuntos al tercero" se guarda como '1'/'0'
 * (seed y Otras configuraciones); el back debe leerlo igual que 'true'/'false'.
 */
class NotificarRadicadoAlTerceroTest extends TestCase
{
    use DatabaseTransactions;

    public function test_acepta_uno_y_true_como_activo(): void
    {
        foreach (['1' => true, 'true' => true, '0' => false, 'false' => false, '' => false] as $valor => $esperado) {
            ConfigVarias::setValor('notificar_radicado_al_tercero', (string) $valor);
            $this->assertSame($esperado, ConfigVarias::getNotificarRadicadoAlTercero(), "valor '$valor'");
        }

        DB::table('config_varias')->where('clave', 'notificar_radicado_al_tercero')->delete();
        $this->assertFalse(ConfigVarias::getNotificarRadicadoAlTercero(), 'Sin la clave no se envían adjuntos');
    }

    public function test_apagado_no_envia_la_notificacion_con_adjuntos(): void
    {
        ConfigVarias::setValor('notificar_radicado_al_tercero', '0');

        $this->assertFalse(AcuseReciboHelper::enviarNotificacionConAdjuntos(new \stdClass));
    }
}
