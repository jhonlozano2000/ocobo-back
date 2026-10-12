<?php

namespace Tests\Unit;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * M08-16 (CWE-209): las respuestas 500 devolvían el mensaje de la excepción (SQL, rutas internas).
 */
class ErroresServidorSinDetalleTest extends TestCase
{
    private function respuestas(): object
    {
        return new class
        {
            use ApiResponseTrait;

            public function error(int $code)
            {
                return $this->errorResponse('Error al guardar', 'SQLSTATE[23000]: Integrity constraint violation', $code);
            }
        };
    }

    public function test_sin_debug_el_detalle_del_500_va_al_log(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        $json = $this->respuestas()->error(500)->getData(true);

        $this->assertSame('Error al guardar', $json['message']);
        $this->assertArrayNotHasKey('errors', $json);
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($msg, $ctx) => str_contains($ctx['error'], 'SQLSTATE'));
    }

    public function test_con_debug_o_errores_de_cliente_se_conserva_el_detalle(): void
    {
        config(['app.debug' => true]);
        $this->assertArrayHasKey('errors', $this->respuestas()->error(500)->getData(true));

        config(['app.debug' => false]);
        $this->assertArrayHasKey('errors', $this->respuestas()->error(422)->getData(true));
    }
}
