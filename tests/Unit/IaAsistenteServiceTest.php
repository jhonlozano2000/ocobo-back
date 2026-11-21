<?php

namespace Tests\Unit;

use App\Http\Requests\Ia\BuscarPrecedentesIaRequest;
use App\Http\Requests\Ia\ProyectarBorradorIaRequest;
use App\Services\Ia\IaAsistenteService;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias de los servicios y validaciones del módulo Asistente de IA (F2-02).
 */
class IaAsistenteServiceTest extends TestCase
{
    public function test_servicio_inicializa_con_defaults_o_parametros(): void
    {
        $service = new IaAsistenteService;
        $this->assertInstanceOf(IaAsistenteService::class, $service);
    }

    public function test_buscar_precedentes_request_reglas_validas(): void
    {
        $request = new BuscarPrecedentesIaRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('consulta', $rules);
        $this->assertArrayHasKey('top_k', $rules);
        $this->assertArrayHasKey('flag_hc', $rules);
        $this->assertArrayHasKey('flag_tutela', $rules);

        $this->assertContains('required', $rules['consulta']);
        $this->assertContains('min:3', $rules['consulta']);
    }

    public function test_proyectar_borrador_request_reglas_validas(): void
    {
        $request = new ProyectarBorradorIaRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('asunto', $rules);
        $this->assertArrayHasKey('contenido', $rules);
        $this->assertArrayHasKey('tipo_solicitud', $rules);
        $this->assertArrayHasKey('top_k', $rules);

        $this->assertContains('required', $rules['asunto']);
        $this->assertContains('min:3', $rules['asunto']);
    }

    public function test_mensajes_en_espanol_configurados(): void
    {
        $requestBuscar = new BuscarPrecedentesIaRequest;
        $messagesBuscar = $requestBuscar->messages();

        $this->assertArrayHasKey('consulta.required', $messagesBuscar);
        $this->assertStringContainsString('obligatorio', $messagesBuscar['consulta.required']);

        $requestProyectar = new ProyectarBorradorIaRequest;
        $messagesProyectar = $requestProyectar->messages();

        $this->assertArrayHasKey('asunto.required', $messagesProyectar);
        $this->assertStringContainsString('obligatorio', $messagesProyectar['asunto.required']);
    }

    public function test_dependencias_permitidas_retorna_array_vacio_si_no_hay_usuario(): void
    {
        $service = new IaAsistenteService;
        $deps = $service->obtenerDependenciasPermitidas(null);

        $this->assertIsArray($deps);
        $this->assertEmpty($deps);
    }
}
