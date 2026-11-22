<?php

namespace Tests\Unit;

use App\Http\Requests\Ia\SugerirDocumentoBlancoRequest;
use App\Services\Ia\IaAsistenteService;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para la sugerencia de texto inicial en documento en blanco
 * (memorando, acta, circular y oficio).
 */
class IaDocumentoBlancoTest extends TestCase
{
    public function test_reglas_de_validacion_exigen_frase_obligatoria_y_tipologia_valida(): void
    {
        $request = new SugerirDocumentoBlancoRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('frase', $rules);
        $this->assertContains('required', $rules['frase']);
        $this->assertContains('min:10', $rules['frase']);
        $this->assertContains('max:240', $rules['frase']);

        $this->assertArrayHasKey('tipo_documento', $rules);
        $this->assertContains('required', $rules['tipo_documento']);
        $this->assertContains('in:memorando,acta,circular,oficio', $rules['tipo_documento']);

        $this->assertArrayHasKey('top_k', $rules);
        $this->assertContains('max:3', $rules['top_k']);
    }

    public function test_mensajes_y_atributos_en_espanol(): void
    {
        $request = new SugerirDocumentoBlancoRequest;
        $messages = $request->messages();
        $attributes = $request->attributes();

        $this->assertArrayHasKey('frase.required', $messages);
        $this->assertArrayHasKey('frase.min', $messages);
        $this->assertArrayHasKey('tipo_documento.in', $messages);
        $this->assertEquals('frase de propósito', $attributes['frase']);
        $this->assertEquals('tipología documental', $attributes['tipo_documento']);
    }

    public function test_servicio_retorna_deshabilitado_cuando_feature_flag_esta_apagado(): void
    {
        $service = new IaAsistenteService(
            baseUrl: 'http://127.0.0.1:8002',
            apiKey: 'test_key',
            timeout: 30,
            enabled: false
        );

        $resultado = $service->sugerirDocumentoBlanco([
            'frase' => 'Solicitar actualización de soportes de facturación a la EPS',
            'tipo_documento' => 'oficio',
        ], null);

        $this->assertFalse($resultado['success']);
        $this->assertEquals('SERVICIO_DESHABILITADO', $resultado['estado']);
        $this->assertEquals('oficio', $resultado['tipo_documento']);
    }

    public function test_contexto_funcionario_sin_usuario_retorna_estructura_segura(): void
    {
        $service = new IaAsistenteService;
        $ctx = $service->obtenerContextoFuncionario(null);

        $this->assertNull($ctx['dependencia_id']);
        $this->assertNull($ctx['dependencia_nombre']);
        $this->assertNull($ctx['cargo_funcionario']);
    }
}
