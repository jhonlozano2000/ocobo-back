<?php

namespace Tests\Unit;

use App\Http\Requests\SanitizedFormRequest;
use PHPUnit\Framework\TestCase;

/**
 * Petición mínima para ejercitar la sanitización de la clase base.
 */
class PeticionSanitizada extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

/**
 * La sanitización de SanitizedFormRequest declaraba `: string` al devolver, en un archivo sin
 * strict_types: todo lo que no fuera texto salía convertido (false => '', true => '1', 7 => '7')
 * en TODOS los FormRequest del back. Con `nullable|boolean` el '' pasaba la validación y la base
 * de datos rechazaba el INSERT en columnas enteras ("Incorrect integer value: ''").
 */
class SanitizacionConservaTiposTest extends TestCase
{
    public function test_los_valores_no_textuales_llegan_intactos_a_la_validacion(): void
    {
        $datos = [
            'esta_completado' => false,
            'notificar' => true,
            'orden' => 0,
            'horas' => 1.5,
            'titulo' => '  Revisar el anexo  ',
        ];

        $sanitizados = PeticionSanitizada::create('/prueba', 'POST', $datos)->validationData();

        $this->assertSame(false, $sanitizados['esta_completado']);
        $this->assertSame(true, $sanitizados['notificar']);
        $this->assertSame(0, $sanitizados['orden']);
        $this->assertSame(1.5, $sanitizados['horas']);
        $this->assertSame('Revisar el anexo', $sanitizados['titulo']);
    }

    public function test_tambien_dentro_de_los_arreglos_anidados(): void
    {
        $datos = ['checklists' => [['item_descripcion' => ' Documento 1 ', 'esta_completado' => false, 'orden' => 0]]];

        $sanitizados = PeticionSanitizada::create('/prueba', 'POST', $datos)->validationData();

        $this->assertSame(
            [['item_descripcion' => 'Documento 1', 'esta_completado' => false, 'orden' => 0]],
            $sanitizados['checklists']
        );
    }
}
