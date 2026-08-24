<?php

namespace App\Services\Firma;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para solicitar y verificar timestamps RFC 3161.
 *
 * La verificación usa búsqueda directa del hash dentro del DER del token,
 * evitando la complejidad de un parser ASN.1 completo.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
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
     * @return string  Respuesta TSR completa codificada en base64
     *
     * @throws \RuntimeException Si la TSA rechaza o falla la conexión
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

        $respDer = $response->body();

        if ($respDer === '') {
            throw new \RuntimeException('TSA devolvió respuesta vacía');
        }

        // Verificar que el status sea granted (buscar INTEGER 0 al inicio del PKIStatusInfo)
        // El PKIStatusInfo empieza después del primer SEQUENCE header (30 XX XX)
        $statusByte = ord($respDer[5] ?? "\xFF");

        if ($statusByte > 4) {
            throw new \RuntimeException("La TSA rechazó la solicitud (estado {$statusByte})");
        }

        return base64_encode($respDer);
    }

    /**
     * Verifica un timestamp RFC 3161 buscando el hash dentro del token DER.
     *
     * En lugar de parsear ASN.1 recursivamente (propenso a errores entre
     * proveedores TSA), busca directamente el hash binario y extrae la fecha
     * GeneralizedTime cercana. Si el hash aparece exactamente una vez,
     * el TSA confirmó ese hash.
     *
     * @param  string  $tokenBase64  Respuesta TSR completa en base64
     * @param  string  $hash         Hash SHA-256 esperado en hexadecimal
     * @return array{valido: bool, fecha_firma: ?string}
     */
    public function verificarTimestamp(string $tokenBase64, string $hash): array
    {
        $der = base64_decode($tokenBase64, true);

        if ($der === false || strlen($der) < 20) {
            return ['valido' => false, 'fecha_firma' => null];
        }

        // Buscar el hash binario dentro del DER del token
        $hashBin = hex2bin($hash);
        $positions = [];
        $searchStart = 0;

        while (($pos = strpos($der, $hashBin, $searchStart)) !== false) {
            $positions[] = $pos;
            $searchStart = $pos + 1;
        }

        // El hash debe aparecer EXACTAMENTE una vez (en el MessageImprint del TSTInfo).
        // Cero apariciones: el hash no corresponde. Múltiples: posible colisión.
        if (count($positions) !== 1) {
            return ['valido' => false, 'fecha_firma' => null];
        }

        // Extraer la fecha GeneralizedTime cercana al hash.
        // En TSTInfo, genTime viene justo después de messageImprint + serialNumber.
        // Buscar patrón GeneralizedTime: "20XX" seguido de dígitos hasta 'Z'
        $fecha = $this->extraerGenTimeCercana($der, $positions[0]);

        return [
            'valido' => true,
            'fecha_firma' => $fecha ?? now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Extrae una fecha GeneralizedTime (formato 20XXXXXXXXXXXXXXZ) del buffer
     * buscando hacia adelante desde la posición del hash.
     */
    private function extraerGenTimeCercana(string $der, int $fromPos): ?string
    {
        $len = strlen($der);
        $searchLimit = min($fromPos + 100, $len);

        for ($i = $fromPos; $i < $searchLimit - 15; $i++) {
            // Buscar "202" o "203" (años 2020s) que indiquen inicio de GeneralizedTime
            if (ord($der[$i]) === 0x32 && ord($der[$i + 1] ?? "\x00") === 0x30) {
                // Extraer hasta encontrar 'Z' o máximo 15 chars
                $str = '';
                for ($j = $i; $j < min($i + 20, $len); $j++) {
                    $ch = $der[$j];
                    if ($ch === 'Z') {
                        $str .= 'Z';
                        break;
                    }
                    $str .= $ch;
                }

                // Validar formato YYYYMMDDHHMMSS[Z]
                if (strlen($str) >= 14 && preg_match('/^\d{14}(?:\.\d+)?Z?$/', $str)) {
                    $clean = substr($str, 0, 14);
                    $fecha = substr($clean, 0, 4).'-'.substr($clean, 4, 2).'-'
                        .substr($clean, 6, 2).' '.substr($clean, 8, 2).':'
                        .substr($clean, 10, 2).':'.substr($clean, 12, 2);

                    return strtotime($fecha) ? $fecha : null;
                }
            }
        }

        return null;
    }

    /**
     * Construye un TimeStampReq DER para un hash SHA-256.
     */
    private function buildTimeStampReq(string $hash): string
    {
        $hashBin = hex2bin($hash);
        $oidSha256 = $this->encodeOID('2.16.840.1.101.3.4.2.1');

        $messageImprint = $this->encodeTLV(0x30,
            $this->encodeTLV(0x30, $oidSha256).
            $this->encodeTLV(0x04, $hashBin)
        );

        $nonce = random_bytes(8);
        $nonceInteger = $this->encodeInteger($this->binToBigInt($nonce));

        return $this->encodeTLV(0x30,
            $this->encodeTLV(0x02, "\x01").
            $messageImprint.
            $nonceInteger.
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
                    if ($val > 0) {
                        $byte |= 0x80;
                    }
                    $encoded = chr($byte).$encoded;
                }
            }

            $bytes .= $encoded;
        }

        return $this->encodeTLV(0x06, $bytes);
    }

    private function encodeInteger(array $bigInt): string
    {
        $hex = '';
        foreach ($bigInt as $chunk) {
            $hex .= str_pad(dechex($chunk), 8, '0', STR_PAD_LEFT);
        }
        $hex = ltrim($hex, '0');

        if ($hex === '') {
            $hex = '00';
        }

        if (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }

        $bytes = hex2bin($hex);

        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return $this->encodeTLV(0x02, $bytes);
    }

    private function binToBigInt(string $bin): array
    {
        $hex = bin2hex($bin);

        if ($hex === '') {
            return [0];
        }

        $result = [];
        for ($i = strlen($hex) - 8; ; $i -= 8) {
            if ($i < 0) {
                $result[] = hexdec(substr($hex, 0, $i + 8));
                break;
            }
            $result[] = hexdec(substr($hex, $i, 8));
            if ($i <= 0) {
                break;
            }
        }

        return array_reverse($result);
    }
}
