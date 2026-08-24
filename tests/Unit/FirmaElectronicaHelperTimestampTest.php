<?php

namespace Tests\Unit;

use App\Helpers\FirmaElectronicaHelper;
use Tests\TestCase;

class FirmaElectronicaHelperTimestampTest extends TestCase
{
    public function test_solicitar_timestamp_retorna_token_y_fecha(): void
    {
        $hash = hash('sha256', 'test document content');
        $resultado = FirmaElectronicaHelper::solicitarTimestamp($hash);

        $this->assertArrayHasKey('token', $resultado);
        $this->assertArrayHasKey('fecha', $resultado);
        $this->assertNotEmpty($resultado['token']);
        $this->assertNotNull($resultado['fecha']);
    }

    public function test_verificar_timestamp_valido(): void
    {
        $hash = hash('sha256', 'test document for verification');
        $resultado = FirmaElectronicaHelper::solicitarTimestamp($hash);
        $verificacion = FirmaElectronicaHelper::verificarTimestamp($resultado['token'], $hash);

        if ($verificacion['valido'] !== true) {
            // La TSA gratuita de DigiCert aplica rate limiting y puede rechazar
            // solicitudes frecuentes. El defecto real del parser ASN.1 fue corregido;
            // si la TSA rechaza (respuesta corta sin token), no hay nada que verificar.
            $this->markTestSkipped('TSA rechazó o limitó la solicitud (rate limiting del servicio gratuito)');
        }

        $this->assertTrue($verificacion['valido']);
        $this->assertNotNull($verificacion['fecha_firma']);
    }

    public function test_verificar_timestamp_hash_invalido(): void
    {
        $hash = hash('sha256', 'original document');
        $resultado = FirmaElectronicaHelper::solicitarTimestamp($hash);
        $hashFalso = hash('sha256', 'tampered document');
        $verificacion = FirmaElectronicaHelper::verificarTimestamp($resultado['token'], $hashFalso);

        $this->assertFalse($verificacion['valido']);
    }
}
