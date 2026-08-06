<?php

namespace App\Services\Firma;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TsaService
{
    private string $tsaUrl;

    public function __construct(?string $tsaUrl = null)
    {
        $this->tsaUrl = $tsaUrl ?? config('firma.tsa_url', 'http://timestamp.digicert.com');
    }

    /**
     * Solicita un timestamp RFC 3161 para un hash SHA-256.
     *
     * @param  string  $hash  Hash SHA-256 en hexadecimal
     * @return string  Token TSR codificado en base64
     */
    public function solicitarTimestamp(string $hash): string
    {
        $requestDer = $this->buildTimeStampReq($hash);

        $response = Http::withHeaders([
            'Content-Type' => 'application/timestamp-query',
            'Accept' => 'application/timestamp-response',
        ])->withBody($requestDer, 'application/octet-stream')
            ->post($this->tsaUrl);

        if ($response->failed()) {
            throw new \RuntimeException("Error al consultar TSA: {$response->status()}");
        }

        $tsr = $response->body();

        if (strlen($tsr) === 0) {
            throw new \RuntimeException('TSA devolvió respuesta vacía');
        }

        return base64_encode($tsr);
    }

    /**
     * Verifica un token de timestamp RFC 3161.
     *
     * @param  string  $tokenBase64  Token TSR en base64
     * @param  string  $hash         Hash SHA-256 esperado en hexadecimal
     * @return array ['valido' => bool, 'fecha_firma' => ?string]
     */
    public function verificarTimestamp(string $tokenBase64, string $hash): array
    {
        $tokenDer = base64_decode($tokenBase64, true);

        if ($tokenDer === false) {
            return ['valido' => false, 'fecha_firma' => null];
        }

        $parsed = $this->parseTimeStampToken($tokenDer);

        if ($parsed === null) {
            return ['valido' => false, 'fecha_firma' => null];
        }

        // Verificar que el hash coincida
        if (strtolower($parsed['message_imprint']) !== strtolower($hash)) {
            Log::warning('Timestamp hash mismatch', [
                'esperado' => $hash,
                'obtenido' => $parsed['message_imprint'],
            ]);

            return ['valido' => false, 'fecha_firma' => $parsed['fecha'] ?? null];
        }

        // Verificar firma del token con certificado TSA
        $firmaValida = $this->verificarFirmaToken($tokenDer, $parsed);

        return [
            'valido' => $firmaValida,
            'fecha_firma' => $parsed['fecha'] ?? null,
        ];
    }

    /**
     * Construye un TimeStampReq DER (ASN.1) para un hash SHA-256.
     */
    private function buildTimeStampReq(string $hash): string
    {
        $hashBin = hex2bin($hash);

        // OID para SHA-256: 2.16.840.1.101.3.4.2.1
        $oidSha256 = $this->encodeOID('2.16.840.1.101.3.4.2.1');

        // MessageImprint ::= SEQUENCE {
        //   hashAlgorithm  AlgorithmIdentifier,
        //   hashedMessage   OCTET STRING
        // }
        $messageImprint = $this->encodeSequence(
            $this->encodeSequence($oidSha256) .
            $this->encodeOctetString($hashBin)
        );

        // Nonce (8 bytes aleatorios para evitar replay)
        $nonce = random_bytes(8);
        $nonceInteger = $this->encodeInteger($this->binToBigInt($nonce));

        // OID para 'no policy' (2.16.840.1.101.3.4.2.1 es SHA-256, usamos 2.5.29.37.0 = anyPolicy)
        // Opcionalmente incluir certReq = TRUE para que el TSA nos devuelva su certificado
        // TimeStampReq ::= SEQUENCE {
        //   version          INTEGER  v1(1),
        //   messageImprint   MessageImprint,
        //   reqPolicy        TSAPolicyId OPTIONAL,
        //   nonce            INTEGER OPTIONAL,
        //   certReq          BOOLEAN DEFAULT FALSE,
        //   extensions       [0] IMPLICIT Extensions OPTIONAL
        // }
        $request = $this->encodeSequence(
            $this->encodeInteger(1) .         // version
            $messageImprint .                  // messageImprint
            $nonceInteger .                    // nonce
            $this->encodeBoolean(true)         // certReq = TRUE
        );

        return $request;
    }

    /**
     * Parsea un token TSR (TimeStampToken) DER y extrae datos relevantes.
     */
    private function parseTimeStampToken(string $tokenDer): ?array
    {
        $offset = 0;
        $contentInfo = $this->parseSequence($tokenDer, $offset);

        if ($contentInfo === null) {
            return null;
        }

        // ContentInfo ::= SEQUENCE { contentType OID, content [0] EXPLICIT ANY }
        $contentTypeOid = $this->parseOID($contentInfo['children'][0]['value'] ?? '');

        // OID para SignedData: 1.2.840.113549.1.7.2
        if ($contentTypeOid !== '1.2.840.113549.1.7.2') {
            return null;
        }

        // Obtener el contenido (SignedData)
        $signedDataTag = $contentInfo['children'][1] ?? null;
        if ($signedDataTag === null) {
            return null;
        }

        $signedDataOffset = 0;
        $signedData = $this->parseSequence($signedDataTag['value'], $signedDataOffset);

        if ($signedData === null) {
            return null;
        }

        // SignedData ::= SEQUENCE {
        //   version, digestAlgorithms, encapContentInfo, certificates, crls, signerInfos
        // }
        // encapContentInfo es el tercer elemento (índice 2)
        $encapContentInfo = $signedData['children'][2] ?? null;
        if ($encapContentInfo === null) {
            return null;
        }

        // EncryptedContentInfo ::= SEQUENCE { contentType OID, contentEncryptionAlgorithm, content [0] }
        $encapOffset = 0;
        $encapSeq = $this->parseSequence($encapContentInfo['value'], $encapOffset);

        if ($encapSeq === null) {
            return null;
        }

        // El contenido está en el tag [0] - TSTInfo
        $tstInfoTag = null;
        foreach ($encapSeq['children'] as $child) {
            if ($child['tag'] === 0xa0) { // [0] IMPLICIT
                $tstInfoTag = $child;
                break;
            }
        }

        if ($tstInfoTag === null) {
            // Buscar en la posición 2 directamente
            $tstInfoTag = $encapSeq['children'][2] ?? null;
        }

        if ($tstInfoTag === null) {
            return null;
        }

        // Parsear TSTInfo
        $tstInfoOffset = 0;
        $tstInfo = $this->parseSequence($tstInfoTag['value'], $tstInfoOffset);

        if ($tstInfo === null) {
            return null;
        }

        // TSTInfo ::= SEQUENCE {
        //   version        INTEGER v1(1),
        //   policy         TSAPolicyId,
        //   messageImprint MessageImprint,
        //   serialNumber   INTEGER,
        //   genTime        GeneralizedTime,
        //   accuracy       Accuracy OPTIONAL,
        //   nonce          INTEGER OPTIONAL,
        //   tsa            [0] GeneralName OPTIONAL,
        //   extensions     [1] IMPLICIT Extensions OPTIONAL
        // }
        $children = $tstInfo['children'];

        if (count($children) < 5) {
            return null;
        }

        // genTime está en posición 4
        $genTime = $this->parseGeneralizedTime($children[4]['value'] ?? '');

        // messageImprint está en posición 2
        $messageImprintSeq = $this->parseSequence($children[2]['value'] ?? '', $dummy = 0);
        $messageImprint = null;
        if ($messageImprintSeq !== null && count($messageImprintSeq['children']) >= 2) {
            $messageImprint = bin2hex($messageImprintSeq['children'][1]['value'] ?? '');
        }

        // Extraer certificados del SignedData (posición 3)
        $certificados = [];
        $certificatesTag = $signedData['children'][3] ?? null;
        if ($certificatesTag !== null && $certificatesTag['tag'] === 0xa0) {
            $certificados = $this->parseCertificates($certificatesTag['value']);
        }

        return [
            'fecha' => $genTime,
            'message_imprint' => $messageImprint,
            'certificados' => $certificados,
            'signed_data' => $signedDataTag['value'] ?? '',
        ];
    }

    /**
     * Verifica la firma del token usando el certificado TSA del token.
     */
    private function verificarFirmaToken(string $tokenDer, array $parsed): bool
    {
        if (empty($parsed['certificados'])) {
            Log::warning('No se encontraron certificados en el token TSA');

            return false;
        }

        // Obtener el certificado TSA (el primero)
        $certPem = $parsed['certificados'][0]['der'] ?? null;
        if ($certPem === null) {
            return false;
        }

        $cert = openssl_x509_read($certPem);
        if ($cert === false) {
            return false;
        }

        // Verificar que el certificado no esté vencido
        $certInfo = openssl_x509_parse($cert);
        if ($certInfo === false) {
            return false;
        }

        $now = time();
        $validFrom = $certInfo['validFrom_time_t'] ?? 0;
        $validTo = $certInfo['validTo_time_t'] ?? 0;

        if ($now < $validFrom || $now > $validTo) {
            Log::warning('Certificado TSA fuera de vigencia', [
                'desde' => date('Y-m-d H:i:s', $validFrom),
                'hasta' => date('Y-m-d H:i:s', $validTo),
            ]);

            return false;
        }

        // Extraer el SignedData completo y parsear
        $signedDataOffset = 0;
        $parsedData = $this->parseSequence($parsed['signed_data'], $signedDataOffset);
        if ($parsedData === null) {
            return false;
        }

        // signerInfos es el último elemento (SET)
        $signerInfos = $parsedData['children'][count($parsedData['children']) - 1] ?? null;
        if ($signerInfos === null) {
            return false;
        }

        // Parsear SignerInfo (SET OF, tomando el primer elemento)
        $signerInfoSet = $this->parseSet($signerInfos['value'] ?? '');
        if ($signerInfoSet === null || count($signerInfoSet['children']) === 0) {
            return false;
        }

        $signerInfoSeq = $this->parseSequence($signerInfoSet['children'][0]['value'] ?? '', $siOffset = 0);
        if ($signerInfoSeq === null) {
            return false;
        }

        $siChildren = $signerInfoSeq['children'];

        // Buscar signedAttrs (tag [0] = 0xa0) y signature (OCTET STRING = 0x04)
        $signedAttrsRaw = null;
        $signatureValue = null;
        $digestAlgorithm = 'SHA256';

        foreach ($siChildren as $child) {
            if ($child['tag'] === 0xa0) {
                // signedAttrs - el contenido que se firma es esta secuencia completa (con tag 0xa0)
                $signedAttrsRaw = $child['value'];
            } elseif ($child['tag'] === 0x04) {
                $signatureValue = $child['value'];
            }
        }

        // Obtener digestAlgorithm (debe estar antes de signedAttrs)
        foreach ($siChildren as $child) {
            if ($child['tag'] === 0x30) { // SEQUENCE = AlgorithmIdentifier
                $algSeq = $this->parseSequence($child['value'], $algOffset = 0);
                if ($algSeq !== null && count($algSeq['children']) > 0) {
                    $oid = $this->parseOID($algSeq['children'][0]['value'] ?? '');
                    $digestAlgorithm = match ($oid) {
                        '2.16.840.1.101.3.4.2.1' => 'SHA256',
                        '1.3.14.3.2.26' => 'SHA1',
                        '2.16.840.1.101.3.4.2.2' => 'SHA384',
                        '2.16.840.1.101.3.4.2.3' => 'SHA512',
                        default => 'SHA256',
                    };
                }
                break;
            }
        }

        if ($signatureValue === null) {
            Log::warning('No se pudo extraer la firma del token TSA');

            return false;
        }

        // Si hay signedAttrs, la firma se verifica sobre el DER completo del tag [0] (con tag y longitud)
        if ($signedAttrsRaw !== null) {
            // Reconstruir el tag [0] completo (tag 0xa0 + longitud + contenido)
            $signedAttrsFull = $this->encodeTLV(0xa0, $signedAttrsRaw);
            $dataToVerify = $signedAttrsFull;
        } else {
            // Sin signedAttrs, la firma es sobre el TSTInfo
            $dataToVerify = $parsed['tst_info'] ?? '';
        }

        // Verificar la firma
        $openAlgo = match ($digestAlgorithm) {
            'SHA256' => OPENSSL_ALGO_SHA256,
            'SHA1' => OPENSSL_ALGO_SHA1,
            'SHA384' => OPENSSL_ALGO_SHA384,
            'SHA512' => OPENSSL_ALGO_SHA512,
            default => OPENSSL_ALGO_SHA256,
        };

        $result = openssl_verify($dataToVerify, $signatureValue, $cert, $openAlgo);

        if ($result !== 1) {
            Log::warning('Verificación de firma TSA falló', ['result' => $result]);
        }

        return $result === 1;
    }

    /**
     * Extrae el algoritmo de digest de SignerInfo.
     */
    private function extractDigestAlgorithm(array $signerInfo): ?string
    {
        // SignerInfo: version, sid, digestAlgorithm, signedAttrs, signatureAlgorithm, signature
        $children = $signerInfo['children'];
        if (count($children) < 5) {
            return null;
        }

        // digestAlgorithm está en posición 2
        $digestAlgSeq = $this->parseSequence($children[2]['value'] ?? '', $daOffset = 0);
        if ($digestAlgSeq === null || count($digestAlgSeq['children']) === 0) {
            return null;
        }

        $oid = $this->parseOID($digestAlgSeq['children'][0]['value'] ?? '');

        return match ($oid) {
            '2.16.840.1.101.3.4.2.1' => 'SHA256',
            '1.3.14.3.2.26' => 'SHA1',
            '2.16.840.1.101.3.4.2.2' => 'SHA384',
            '2.16.840.1.101.3.4.2.3' => 'SHA512',
            default => 'SHA256',
        };
    }

    /**
     * Parsea certificados X.509 de un SET de certificados DER.
     */
    private function parseCertificates(string $certsDer): array
    {
        $certs = [];
        $offset = 0;

        // Es un SET OF Certificate
        $set = $this->parseSet($certsDer, $offset);
        if ($set === null) {
            return $certs;
        }

        foreach ($set['children'] as $certChild) {
            // Cada cert es un SEQUENCE
            $certSeq = $this->parseSequence($certChild['value'], $certOffset = 0);
            if ($certSeq !== null) {
                $certDer = $certChild['value'];
                $certInfo = openssl_x509_read('-----BEGIN CERTIFICATE-----' . "\n" .
                    chunk_split(base64_encode($certDer), 64, "\n") .
                    '-----END CERTIFICATE-----');

                $certs[] = [
                    'der' => '-----BEGIN CERTIFICATE-----' . "\n" .
                        chunk_split(base64_encode($certDer), 64, "\n") .
                        '-----END CERTIFICATE-----',
                    'info' => is_resource($certInfo) ? openssl_x509_parse($certInfo) : null,
                ];
            }
        }

        return $certs;
    }

    // ============================================================
    // ASN.1 DER Encoding Helpers
    // ============================================================

    private function encodeSequence(string $content): string
    {
        return $this->encodeTLV(0x30, $content);
    }

    private function encodeSet(string $content): string
    {
        return $this->encodeTLV(0x31, $content);
    }

    private function encodeOctetString(string $content): string
    {
        return $this->encodeTLV(0x04, $content);
    }

    private function encodeBoolean(bool $value): string
    {
        return $this->encodeTLV(0x01, $value ? "\xFF" : "\x00");
    }

    private function encodeInteger(int|array $value): string
    {
        if (is_int($value)) {
            $bytes = $this->bigIntToBytes([$value]);
        } else {
            $bytes = $this->bigIntToBytes($value);
        }

        // Agregar byte de signo si el bit más significativo está activo
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }

        return $this->encodeTLV(0x02, $bytes);
    }

    private function encodeOID(string $oid): string
    {
        $parts = explode('.', $oid);
        $bytes = chr((intval($parts[0]) * 40) + intval($parts[1]));

        for ($i = 2; $i < count($parts); $i++) {
            $val = intval($parts[$i]);
            $encoded = $this->encodeVariableLengthInteger($val);
            $bytes .= $encoded;
        }

        return $this->encodeTLV(0x06, $bytes);
    }

    private function encodeVariableLengthInteger(int $value): string
    {
        if ($value < 128) {
            return chr($value);
        }

        $bytes = [];
        $temp = $value;

        while ($temp > 0) {
            $bytes[] = $temp & 0x7F;
            $temp >>= 7;
        }

        // Output MSB to LSB, setting high bit on all except the last
        $result = '';

        for ($i = count($bytes) - 1; $i >= 0; $i--) {
            $byte = $bytes[$i];

            if ($i > 0) {
                $byte |= 0x80;
            }

            $result .= chr($byte);
        }

        return $result;
    }

    private function encodeTLV(int $tag, string $value): string
    {
        $length = strlen($value);

        if ($length < 128) {
            return chr($tag) . chr($length) . $value;
        }

        $lengthBytes = '';
        $temp = $length;

        while ($temp > 0) {
            $lengthBytes = chr($temp & 0xFF) . $lengthBytes;
            $temp >>= 8;
        }

        $lengthByte = 0x80 | strlen($lengthBytes);

        return chr($tag) . chr($lengthByte) . $lengthBytes . $value;
    }

    private function binToBigInt(string $bin): array
    {
        $hex = bin2hex($bin);

        if (strlen($hex) === 0) {
            return [0];
        }

        $result = [];
        $i = 0;

        while ($i < strlen($hex)) {
            $chunk = substr($hex, $i, 8);
            $result[] = hexdec($chunk);
            $i += 8;
        }

        return $result;
    }

    private function bigIntToBytes(array $bigInt): string
    {
        $hex = '';

        foreach ($bigInt as $chunk) {
            $hex .= str_pad(dechex($chunk), 8, '0', STR_PAD_LEFT);
        }

        // Remover ceros leading
        $hex = ltrim($hex, '0');

        if (strlen($hex) === 0) {
            return "\x00";
        }

        // Asegurar longitud par
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return hex2bin($hex);
    }

    // ============================================================
    // ASN.1 DER Parsing Helpers
    // ============================================================

    private function parseSequence(string $der, int &$offset = 0): ?array
    {
        return $this->parseTLV($der, $offset, 0x30);
    }

    private function parseSet(string $der, int &$offset = 0): ?array
    {
        return $this->parseTLV($der, $offset, 0x31);
    }

    private function parseTLV(string $der, int &$offset, int $expectedTag): ?array
    {
        if ($offset >= strlen($der)) {
            return null;
        }

        $tag = ord($der[$offset]);
        $offset++;

        if (($tag & 0x1F) === 0x1F) {
            // Tag compuesto (más de un byte)
            $tag = $tag & 0x1F;

            while ($offset < strlen($der)) {
                $byte = ord($der[$offset]);
                $offset++;
                $tag = ($tag << 7) | ($byte & 0x7F);

                if (($byte & 0x80) === 0) {
                    break;
                }
            }
        }

        // Parsear longitud
        if ($offset >= strlen($der)) {
            return null;
        }

        $lengthByte = ord($der[$offset]);
        $offset++;

        if ($lengthByte < 0x80) {
            $length = $lengthByte;
        } elseif ($lengthByte === 0x80) {
            // Longitud indefinida - no soportada
            return null;
        } elseif ($lengthByte === 0xFF) {
            return null;
        } else {
            $numLengthBytes = $lengthByte & 0x7F;

            if ($offset + $numLengthBytes > strlen($der)) {
                return null;
            }

            $length = 0;

            for ($i = 0; $i < $numLengthBytes; $i++) {
                $length = ($length << 8) | ord($der[$offset]);
                $offset++;
            }
        }

        if ($offset + $length > strlen($der)) {
            return null;
        }

        $value = substr($der, $offset, $length);
        $offset += $length;

        return ['tag' => $tag, 'length' => $length, 'value' => $value];
    }

    private function parseOID(string $der): string
    {
        if (strlen($der) === 0) {
            return '';
        }

        $firstByte = ord($der[0]);
        $oid = intdiv($firstByte, 40) . '.' . ($firstByte % 40);

        $value = 0;
        $offset = 1;

        while ($offset < strlen($der)) {
            $byte = ord($der[$offset]);
            $offset++;

            $value = ($value << 7) | ($byte & 0x7F);

            if (($byte & 0x80) === 0) {
                $oid .= '.' . $value;
                $value = 0;
            }
        }

        return $oid;
    }

    private function parseGeneralizedTime(string $der): ?string
    {
        // GeneralizedTime ::= VisibleString (formato: YYYYMMDDHHMMSSZ o YYYYMMDDHHMMSS.fffZ)
        if (strlen($der) < 15) {
            return null;
        }

        $str = $der;

        // Remover 'Z' al final si existe
        if (substr($str, -1) === 'Z') {
            $str = substr($str, 0, -1);
        }

        // Remover fractional seconds si existen
        if (strpos($str, '.') !== false) {
            $str = substr($str, 0, strpos($str, '.'));
        }

        if (strlen($str) < 14) {
            return null;
        }

        $year = substr($str, 0, 4);
        $month = substr($str, 4, 2);
        $day = substr($str, 6, 2);
        $hour = substr($str, 8, 2);
        $min = substr($str, 10, 2);
        $sec = substr($str, 12, 2);

        $fecha = "{$year}-{$month}-{$day} {$hour}:{$min}:{$sec}";

        // Validar que sea una fecha válida
        $timestamp = strtotime($fecha);

        return $timestamp ? $fecha : null;
    }
}
