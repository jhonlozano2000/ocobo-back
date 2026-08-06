<?php

namespace Tests\Unit;

use App\Services\Firma\TsaService;
use Tests\TestCase;

class TsaServiceTest extends TestCase
{
    public function test_solicitar_timestamp_retorna_token_base64(): void
    {
        $hash = hash('sha256', 'documento de prueba');
        $service = new TsaService();

        $token = $service->solicitarTimestamp($hash);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        // Verificar que es base64 válido
        $this->assertNotFalse(base64_decode($token, true));
    }

    public function test_verificar_timestamp_retorna_estructura_correcta(): void
    {
        $hash = hash('sha256', 'documento de prueba');
        $service = new TsaService();

        $token = $service->solicitarTimestamp($hash);
        $resultado = $service->verificarTimestamp($token, $hash);

        $this->assertArrayHasKey('valido', $resultado);
        $this->assertArrayHasKey('fecha_firma', $resultado);
        $this->assertIsBool($resultado['valido']);
    }

    public function test_verificar_timestamp_con_hash_invalido_falla(): void
    {
        $hash = hash('sha256', 'documento de prueba');
        $service = new TsaService();

        $token = $service->solicitarTimestamp($hash);
        $hashFalso = hash('sha256', 'documento alterado');
        $resultado = $service->verificarTimestamp($token, $hashFalso);

        $this->assertFalse($resultado['valido']);
    }

    public function test_verificar_timestamp_con_token_invalido_retorna_no_valido(): void
    {
        $service = new TsaService();
        $resultado = $service->verificarTimestamp(base64_encode('token_invalido'), hash('sha256', 'test'));

        $this->assertFalse($resultado['valido']);
    }

    public function test_url_tsa_se_configura_desde_env(): void
    {
        $service = new TsaService();
        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('tsaUrl');
        $prop->setAccessible(true);

        $this->assertNotEmpty($prop->getValue($service));
    }
}
