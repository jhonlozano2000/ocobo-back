<?php

namespace App\Services\Firma;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Servicio para solicitar y verificar timestamps RFC 3161.
 *
 * M06-01: la verificación es criptográfica. Antes solo se buscaba el hash con
 * strpos dentro del DER, de modo que cualquier blob que contuviera el hash
 * pasaba por sello válido. Ahora se comprueba:
 *  - estado PKIStatus granted/grantedWithMods de la respuesta;
 *  - que el token sea un SignedData con contenido id-ct-TSTInfo;
 *  - la firma CMS sobre el TSTInfo;
 *  - que el certificado firmante encadene a una raíz de confianza
 *    (firma.tsa_ca_file) y tenga EKU timeStamping;
 *  - que el messageImprint sea SHA-256 del hash esperado;
 *  - el nonce, cuando se verifica la respuesta de la propia solicitud.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
class TsaService
{
    private const OID_SHA256 = '2.16.840.1.101.3.4.2.1';

    private const OID_SIGNED_DATA = '1.2.840.113549.1.7.2';

    private const OID_TST_INFO = '1.2.840.113549.1.9.16.1.4';

    /** X509_PURPOSE_TIMESTAMP_SIGN de OpenSSL (PHP no expone la constante). */
    private const PROPOSITO_SELLADO = 9;

    private string $tsaUrl;

    public function __construct(?string $tsaUrl = null)
    {
        $this->tsaUrl = $tsaUrl ?? config('firma.tsa_url', 'http://timestamp.digicert.com');
    }

    /**
     * Solicita un timestamp RFC 3161 para un hash SHA-256 y lo verifica antes de devolverlo.
     *
     * @param  string  $hash  Hash SHA-256 en hexadecimal
     * @return array{token: string, fecha: string} Respuesta TSR en base64 y genTime del sello
     *
     * @throws \RuntimeException Si la TSA rechaza, falla la conexión o el sello no es verificable
     */
    public function solicitarTimestamp(string $hash): array
    {
        if (! $this->esHashSha256($hash)) {
            throw new \RuntimeException('El hash a sellar no es SHA-256 hexadecimal');
        }

        $nonce = random_bytes(8);
        $requestDer = $this->buildTimeStampReq($hash, $nonce);

        $response = Http::timeout((int) config('firma.tsa_timeout', 10))
            ->withHeaders([
                'Content-Type' => 'application/timestamp-query',
                'Accept' => 'application/timestamp-response',
            ])->withBody($requestDer, 'application/timestamp-query')
            ->post($this->tsaUrl);

        if ($response->failed()) {
            throw new \RuntimeException("Error al consultar TSA: {$response->status()}");
        }

        $respDer = $response->body();

        if ($respDer === '') {
            throw new \RuntimeException('TSA devolvió respuesta vacía');
        }

        $verificacion = $this->verificarDer($respDer, $hash, $nonce);

        if (! $verificacion['valido']) {
            throw new \RuntimeException('La TSA devolvió un sello no verificable: '.$verificacion['motivo']);
        }

        return [
            'token' => base64_encode($respDer),
            'fecha' => $verificacion['fecha_firma'],
        ];
    }

    /**
     * Verifica un timestamp RFC 3161 (respuesta TSR completa o solo el TimeStampToken).
     *
     * @param  string  $tokenBase64  Respuesta TSR o token en base64
     * @param  string  $hash  Hash SHA-256 esperado en hexadecimal
     * @param  string|null  $nonce  Nonce binario enviado en la solicitud, si se conoce
     * @return array{valido: bool, fecha_firma: ?string, motivo: ?string}
     */
    public function verificarTimestamp(string $tokenBase64, string $hash, ?string $nonce = null): array
    {
        $der = base64_decode($tokenBase64, true);

        if ($der === false || $der === '') {
            return $this->invalido('El token no es base64 válido');
        }

        return $this->verificarDer($der, $hash, $nonce);
    }

    /**
     * @return array{valido: bool, fecha_firma: ?string, motivo: ?string}
     */
    private function verificarDer(string $der, string $hash, ?string $nonce): array
    {
        if (! $this->esHashSha256($hash)) {
            return $this->invalido('El hash esperado no es SHA-256 hexadecimal');
        }

        try {
            $token = $this->extraerToken($der);
            $tstInfo = $this->extraerTstInfo($token);
            $this->verificarFirmaCms($token);
            $datos = $this->parsearTstInfo($tstInfo);
        } catch (\RuntimeException $e) {
            return $this->invalido($e->getMessage());
        }

        if ($datos['algoritmo'] !== $this->encodeOID(self::OID_SHA256)) {
            return $this->invalido('El sello no usa SHA-256');
        }

        if (! hash_equals(hex2bin($hash), $datos['huella'])) {
            return $this->invalido('El sello corresponde a otro hash');
        }

        if ($nonce !== null && ltrim($nonce, "\x00") !== ltrim($datos['nonce'] ?? '', "\x00")) {
            return $this->invalido('El nonce del sello no coincide con el de la solicitud');
        }

        return ['valido' => true, 'fecha_firma' => $datos['fecha'], 'motivo' => null];
    }

    /**
     * @return array{valido: false, fecha_firma: null, motivo: string}
     */
    private function invalido(string $motivo): array
    {
        return ['valido' => false, 'fecha_firma' => null, 'motivo' => $motivo];
    }

    private function esHashSha256(string $hash): bool
    {
        return strlen($hash) === 64 && ctype_xdigit($hash);
    }

    /**
     * Devuelve el TimeStampToken (ContentInfo DER). Acepta un TimeStampResp, cuyo
     * estado debe ser granted (0) o grantedWithMods (1), o directamente el token.
     */
    private function extraerToken(string $der): string
    {
        $raiz = $this->leerTlv($der, 0);

        if ($raiz[0] !== 0x30 || $raiz[2] !== strlen($der)) {
            throw new \RuntimeException('La respuesta no es un DER válido');
        }

        $hijos = $this->hijos($der, $raiz);

        if (($hijos[0][0] ?? null) === 0x06) {
            return $der;
        }

        $estadoInfo = $this->esperar($hijos, 0, 0x30, 'PKIStatusInfo');
        $estado = ltrim($this->valor($der, $this->esperar($this->hijos($der, $estadoInfo), 0, 0x02, 'PKIStatus')), "\x00");

        if ($estado !== '' && $estado !== "\x01") {
            throw new \RuntimeException('La TSA rechazó la solicitud (estado '.ord($estado[0]).')');
        }

        $token = $this->esperar($hijos, 1, 0x30, 'TimeStampToken');

        return $this->tlvCompleto($der, $token);
    }

    /**
     * Extrae el TSTInfo encapsulado en el SignedData del token.
     */
    private function extraerTstInfo(string $token): string
    {
        $contentInfo = $this->hijos($token, $this->leerTlv($token, 0));

        if ($this->valor($token, $this->esperar($contentInfo, 0, 0x06, 'contentType')) !== $this->contenidoOID(self::OID_SIGNED_DATA)) {
            throw new \RuntimeException('El token no es un SignedData');
        }

        $explicito = $this->esperar($contentInfo, 1, 0xA0, 'content');
        $signedData = $this->hijos($token, $this->esperar($this->hijos($token, $explicito), 0, 0x30, 'SignedData'));
        $encap = $this->hijos($token, $this->esperar($signedData, 2, 0x30, 'encapContentInfo'));

        if ($this->valor($token, $this->esperar($encap, 0, 0x06, 'eContentType')) !== $this->contenidoOID(self::OID_TST_INFO)) {
            throw new \RuntimeException('El token no contiene un TSTInfo');
        }

        $eContent = $this->esperar($this->hijos($token, $this->esperar($encap, 1, 0xA0, 'eContent')), 0, 0x04, 'eContent');

        return $this->valor($token, $eContent);
    }

    /**
     * Verifica la firma CMS del token, la cadena del firmante hasta una raíz de
     * confianza y que el firmante tenga EKU timeStamping.
     */
    private function verificarFirmaCms(string $token): void
    {
        $caFile = $this->archivoCa();

        if ($caFile === null) {
            throw new \RuntimeException('No hay certificados raíz configurados para verificar la TSA (TSA_CA_FILE)');
        }

        $entrada = tempnam(sys_get_temp_dir(), 'tst');
        $firmantes = tempnam(sys_get_temp_dir(), 'tsc');
        $intermedios = tempnam(sys_get_temp_dir(), 'tsi');

        try {
            file_put_contents($entrada, $token);

            while (openssl_error_string() !== false);

            // NOVERIFY omite solo la cadena (OpenSSL la valida con propósito S/MIME, que
            // los certificados TSA no tienen); la firma sobre el contenido sí se verifica.
            $firmaOk = openssl_cms_verify($entrada, OPENSSL_CMS_BINARY | OPENSSL_CMS_NOVERIFY, $firmantes,
                [], null, null, null, null, OPENSSL_ENCODING_DER);

            if ($firmaOk !== true) {
                throw new \RuntimeException('La firma CMS del sello no es válida');
            }

            preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', (string) file_get_contents($firmantes), $pem);

            if (empty($pem[0])) {
                throw new \RuntimeException('El sello no incluye el certificado de la TSA');
            }

            $certificados = [];
            $pkcs7 = "-----BEGIN PKCS7-----\n".chunk_split(base64_encode($token), 64, "\n")."-----END PKCS7-----\n";
            openssl_pkcs7_read($pkcs7, $certificados);
            file_put_contents($intermedios, implode("\n", $certificados));

            foreach ($pem[0] as $certificado) {
                $valido = openssl_x509_checkpurpose($certificado, self::PROPOSITO_SELLADO, [$caFile],
                    $certificados ? $intermedios : null);

                if ($valido !== true) {
                    throw new \RuntimeException('El certificado de la TSA no es de confianza o no está autorizado para sellar tiempo');
                }
            }
        } finally {
            @unlink($entrada);
            @unlink($firmantes);
            @unlink($intermedios);
        }
    }

    /**
     * Raíces de confianza: firma.tsa_ca_file o, en su defecto, el almacén de OpenSSL/cURL de PHP.
     */
    private function archivoCa(): ?string
    {
        $candidatos = [
            config('firma.tsa_ca_file'),
            ini_get('openssl.cafile'),
            ini_get('curl.cainfo'),
            openssl_get_cert_locations()['default_cert_file'] ?? null,
        ];

        foreach ($candidatos as $ruta) {
            if (is_string($ruta) && $ruta !== '' && is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * @return array{algoritmo: string, huella: string, fecha: string, nonce: ?string}
     */
    private function parsearTstInfo(string $tstInfo): array
    {
        $raiz = $this->leerTlv($tstInfo, 0);

        if ($raiz[0] !== 0x30 || $raiz[2] !== strlen($tstInfo)) {
            throw new \RuntimeException('TSTInfo inválido');
        }

        $campos = $this->hijos($tstInfo, $raiz);
        $imprint = $this->hijos($tstInfo, $this->esperar($campos, 2, 0x30, 'messageImprint'));
        $algoritmo = $this->hijos($tstInfo, $this->esperar($imprint, 0, 0x30, 'hashAlgorithm'));
        $genTime = $this->valor($tstInfo, $this->esperar($campos, 4, 0x18, 'genTime'));

        if (! preg_match('/^(\d{14})(?:\.\d+)?Z$/', $genTime, $m)) {
            throw new \RuntimeException('genTime del sello inválido');
        }

        // Tras genTime: accuracy (SEQUENCE), ordering (BOOLEAN) y nonce (INTEGER), todos opcionales
        $nonce = null;
        foreach (array_slice($campos, 5) as $campo) {
            if ($campo[0] === 0x02) {
                $nonce = $this->valor($tstInfo, $campo);
                break;
            }
        }

        return [
            'algoritmo' => $this->tlvCompleto($tstInfo, $this->esperar($algoritmo, 0, 0x06, 'OID')),
            'huella' => $this->valor($tstInfo, $this->esperar($imprint, 1, 0x04, 'hashedMessage')),
            'fecha' => Carbon::createFromFormat('YmdHis', $m[1], 'UTC')
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i:s'),
            'nonce' => $nonce,
        ];
    }

    /**
     * Lee un TLV DER en $pos.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} [etiqueta, inicio del valor, fin, inicio del TLV]
     */
    private function leerTlv(string $der, int $pos, ?int $limite = null): array
    {
        $limite ??= strlen($der);

        if ($pos + 2 > $limite) {
            throw new \RuntimeException('DER truncado');
        }

        $tag = ord($der[$pos]);
        $len = ord($der[$pos + 1]);
        $inicio = $pos + 2;

        if (($tag & 0x1F) === 0x1F) {
            throw new \RuntimeException('Etiqueta DER no soportada');
        }

        if ($len & 0x80) {
            $bytes = $len & 0x7F;

            if ($bytes === 0 || $bytes > 4 || $inicio + $bytes > $limite) {
                throw new \RuntimeException('Longitud DER inválida');
            }

            $len = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $len = ($len << 8) | ord($der[$inicio++]);
            }
        }

        if ($inicio + $len > $limite) {
            throw new \RuntimeException('DER truncado');
        }

        return [$tag, $inicio, $inicio + $len, $pos];
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private function hijos(string $der, array $tlv): array
    {
        $hijos = [];
        $pos = $tlv[1];

        while ($pos < $tlv[2]) {
            $hijo = $this->leerTlv($der, $pos, $tlv[2]);
            $hijos[] = $hijo;
            $pos = $hijo[2];
        }

        return $hijos;
    }

    private function esperar(array $hijos, int $indice, int $tag, string $nombre): array
    {
        if (($hijos[$indice][0] ?? null) !== $tag) {
            throw new \RuntimeException("Estructura del sello inválida ({$nombre})");
        }

        return $hijos[$indice];
    }

    private function valor(string $der, array $tlv): string
    {
        return substr($der, $tlv[1], $tlv[2] - $tlv[1]);
    }

    private function tlvCompleto(string $der, array $tlv): string
    {
        return substr($der, $tlv[3], $tlv[2] - $tlv[3]);
    }

    private function contenidoOID(string $oid): string
    {
        return substr($this->encodeOID($oid), 2);
    }

    /**
     * Construye un TimeStampReq DER para un hash SHA-256 con el nonce dado.
     */
    private function buildTimeStampReq(string $hash, string $nonce): string
    {
        $messageImprint = $this->encodeTLV(0x30,
            $this->encodeTLV(0x30, $this->encodeOID(self::OID_SHA256)).
            $this->encodeTLV(0x04, hex2bin($hash))
        );

        // INTEGER positivo: sin ceros a la izquierda y con 0x00 si el bit alto está encendido
        $nonceBytes = ltrim($nonce, "\x00");
        if ($nonceBytes === '' || ord($nonceBytes[0]) & 0x80) {
            $nonceBytes = "\x00".$nonceBytes;
        }

        return $this->encodeTLV(0x30,
            $this->encodeTLV(0x02, "\x01").
            $messageImprint.
            $this->encodeTLV(0x02, $nonceBytes).
            $this->encodeTLV(0x01, "\xFF")
        );
    }

    private function encodeTLV(int $tag, string $value): string
    {
        $length = strlen($value);

        if ($length < 128) {
            return chr($tag).chr($length).$value;
        }

        $lenBytes = '';
        $tmp = $length;
        while ($tmp > 0) {
            $lenBytes = chr($tmp & 0xFF).$lenBytes;
            $tmp >>= 8;
        }

        return chr($tag).chr(0x80 | strlen($lenBytes)).$lenBytes.$value;
    }

    private function encodeOID(string $oid): string
    {
        $parts = explode('.', $oid);
        $bytes = chr((intval($parts[0]) * 40) + intval($parts[1]));

        for ($i = 2; $i < count($parts); $i++) {
            $val = intval($parts[$i]);
            $encoded = '';

            if ($val === 0) {
                $encoded = "\x00";
            } else {
                while ($val > 0) {
                    $byte = $val & 0x7F;
                    $val >>= 7;
                    if (! empty($encoded)) {
                        $byte |= 0x80;
                    }
                    $encoded = chr($byte).$encoded;
                }
            }

            $bytes .= $encoded;
        }

        return $this->encodeTLV(0x06, $bytes);
    }
}
