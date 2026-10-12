<?php

namespace Tests\Feature\Firma;

use App\Services\Firma\TsaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * M06-01: el sello de tiempo RFC 3161 se verifica criptográficamente (firma CMS,
 * cadena hasta una raíz de confianza, EKU timeStamping, huella y nonce), no con strpos.
 *
 * Fixtures en tests/Fixtures/tsa, generados con `openssl ts` (CA de prueba a 100 años):
 *  - valid.tsr: sello de la TSA de prueba para HASH con nonce NONCE.
 *  - rogue.tsr: mismo sello firmado por una TSA de otra CA.
 *  - nots.tst: el mismo TSTInfo firmado por un certificado de la CA sin EKU timeStamping.
 */
class TsaServiceTest extends TestCase
{
    private const HASH = '06957cf46592d4231d25293f815b0bf5a9f0145536fd607840bfd92958bfe60d';

    private const NONCE = '26C803317EDC95DC';

    private TsaService $tsa;

    protected function setUp(): void
    {
        parent::setUp();

        config(['firma.tsa_ca_file' => $this->fixture('ca.pem')]);
        $this->tsa = new TsaService('https://tsa.test');
    }

    private function fixture(string $nombre): string
    {
        return base_path('tests/Fixtures/tsa/'.$nombre);
    }

    private function token(string $nombre): string
    {
        return base64_encode(file_get_contents($this->fixture($nombre)));
    }

    public function test_sello_valido_se_verifica_con_su_fecha_y_nonce(): void
    {
        $resultado = $this->tsa->verificarTimestamp($this->token('valid.tsr'), self::HASH, hex2bin(self::NONCE));

        $this->assertTrue($resultado['valido'], (string) $resultado['motivo']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $resultado['fecha_firma']);
    }

    public function test_blob_sin_firma_que_contiene_el_hash_no_es_valido(): void
    {
        // Lo que antes bastaba: que el hash apareciera una vez en el DER
        $falso = "\x30\x24\x04\x20".hex2bin(self::HASH)."\x18\x0F20260101000000Z";

        $resultado = $this->tsa->verificarTimestamp(base64_encode($falso), self::HASH);

        $this->assertFalse($resultado['valido']);
        $this->assertNull($resultado['fecha_firma']);
    }

    public function test_sello_alterado_no_es_valido(): void
    {
        $der = file_get_contents($this->fixture('valid.tsr'));
        $pos = strpos($der, hex2bin(self::HASH));
        // Se altera el número de serie del TSTInfo, que sigue a la huella
        $der[$pos + 34] = chr(ord($der[$pos + 34]) ^ 0x01);

        $resultado = $this->tsa->verificarTimestamp(base64_encode($der), self::HASH);

        $this->assertFalse($resultado['valido']);
        $this->assertSame('La firma CMS del sello no es válida', $resultado['motivo']);
    }

    public function test_sello_de_otro_hash_no_es_valido(): void
    {
        $resultado = $this->tsa->verificarTimestamp($this->token('valid.tsr'), hash('sha256', 'otro documento'));

        $this->assertFalse($resultado['valido']);
        $this->assertSame('El sello corresponde a otro hash', $resultado['motivo']);
    }

    public function test_sello_de_tsa_no_confiable_no_es_valido(): void
    {
        $resultado = $this->tsa->verificarTimestamp($this->token('rogue.tsr'), self::HASH);

        $this->assertFalse($resultado['valido']);
        $this->assertStringContainsString('no es de confianza', $resultado['motivo']);
    }

    public function test_firmante_sin_eku_de_sellado_no_es_valido(): void
    {
        $resultado = $this->tsa->verificarTimestamp($this->token('nots.tst'), self::HASH);

        $this->assertFalse($resultado['valido']);
        $this->assertStringContainsString('no está autorizado para sellar tiempo', $resultado['motivo']);
    }

    public function test_nonce_distinto_no_es_valido(): void
    {
        $resultado = $this->tsa->verificarTimestamp($this->token('valid.tsr'), self::HASH, random_bytes(8));

        $this->assertFalse($resultado['valido']);
        $this->assertSame('El nonce del sello no coincide con el de la solicitud', $resultado['motivo']);
    }

    public function test_solicitud_usa_oid_sha256_correcto_y_rechaza_respuesta_de_otra_solicitud(): void
    {
        Http::fake(['https://tsa.test' => Http::response(file_get_contents($this->fixture('valid.tsr')))]);

        try {
            $this->tsa->solicitarTimestamp(self::HASH);
            $this->fail('Se aceptó un sello con un nonce distinto al de la solicitud');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('nonce', $e->getMessage());
        }

        Http::assertSent(function (Request $request) {
            // AlgorithmIdentifier SHA-256 (2.16.840.1.101.3.4.2.1) seguido de la huella
            return str_contains(bin2hex($request->body()), '300b0609608648016503040201'.'0420'.self::HASH);
        });
    }

    public function test_respuesta_rechazada_por_la_tsa_lanza_excepcion(): void
    {
        // TimeStampResp { PKIStatusInfo { status rejection (2) } } sin token
        Http::fake(['https://tsa.test' => Http::response("\x30\x05\x30\x03\x02\x01\x02")]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rechazó la solicitud (estado 2)');

        $this->tsa->solicitarTimestamp(self::HASH);
    }
}
