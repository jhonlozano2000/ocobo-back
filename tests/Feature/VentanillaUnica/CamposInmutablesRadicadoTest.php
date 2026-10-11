<?php

namespace Tests\Feature\VentanillaUnica;

use App\Http\Requests\Ventanilla\Enviados\StoreRadicadoEnviadoRequest;
use App\Http\Requests\Ventanilla\Recibidos\StoreRadicadoReciboRequest;
use Tests\TestCase;

/**
 * M01-02 / M02-07: el consecutivo oficial (num_radicado) y la autoría (usuario_crea,
 * uploaded_by / subido_por) no se aceptan del cliente. Sin regla, el campo no llega a
 * validated(), que es lo que reciben store() y update().
 */
class CamposInmutablesRadicadoTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'recibidos' => [StoreRadicadoReciboRequest::class, ['num_radicado', 'usuario_crea', 'uploaded_by']],
            'enviados' => [StoreRadicadoEnviadoRequest::class, ['num_radicado', 'usuario_crea', 'subido_por']],
        ];
    }

    /**
     * @dataProvider casos
     */
    public function test_no_acepta_consecutivo_ni_autoria_del_cliente(string $request, array $campos): void
    {
        $rules = (new $request)->rules();

        foreach ($campos as $campo) {
            $this->assertArrayNotHasKey($campo, $rules, $campo);
        }
    }
}
