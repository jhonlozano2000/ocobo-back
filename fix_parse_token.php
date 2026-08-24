<?php

/**
 * Reemplaza parseTimeStampToken con implementación de navegación por offsets.
 */

$file = __DIR__.'/app/Services/Firma/TsaService.php';
$c = file_get_contents($file);

$startMarker = '    private function parseTimeStampToken';
$endMarker = '    /**'.PHP_EOL.'     * Verifica la firma del token';

$start = strpos($c, $startMarker);
$end = strpos($c, $endMarker);

if ($start === false || $end === false) {
    // Fallback sin CRLF
    $endMarker = "/**\n     * Verifica la firma del token";
    $end = strpos($c, $endMarker);
}

if ($start === false || $end === false || $end <= $start) {
    die("ERROR: no se encontraron los marcadores\n");
}

$nuevo = <<<'PHP'
    private function parseTimeStampToken(string $tokenDer): ?array
    {
        // Navegación por offsets explícita — SIN recursión en certificados.
        $offset = 0;

        // ContentInfo ::= SEQUENCE { contentType OID, content [0] EXPLICIT }
        $contentInfo = $this->parseTLV($tokenDer, $offset);
        if ($contentInfo === null || $contentInfo['tag'] !== 0x30) {
            return null;
        }

        // Parsear el contenido del ContentInfo
        $ciOffset = 0;
        $oidTlv = $this->parseTLV($contentInfo['value'], $ciOffset);
        if ($oidTlv === null || $oidTlv['tag'] !== 0x06) {
            return null;
        }
        if ($this->parseOID($oidTlv['value']) !== '1.2.840.113549.1.7.2') {
            return null;
        }

        $a0Tlv = $this->parseTLV($contentInfo['value'], $ciOffset);
        if ($a0Tlv === null || $a0Tlv['tag'] !== 0xa0) {
            return null;
        }

        // SignedData ::= SEQUENCE { version, digestAlgorithms SET,
        //   encapContentInfo SEQUENCE, certificates [0] IMPLICIT OPTIONAL,
        //   crls [1] IMPLICIT OPTIONAL, signerInfos SET }
        $sdOffset = 0;
        $signedDataTlv = $this->parseSequence($a0Tlv['value'], $sdOffset);
        if ($signedDataTlv === null) {
            return null;
        }

        // Navegar los hijos de SignedData por offset para evitar
        // recursión profunda dentro de los certificados.
        $sdValueOffset = 0;
        $sdChildren = [];
        while ($sdValueOffset < strlen($signedDataTlv['value'])) {
            $childStart = $sdValueOffset;
            $child = $this->parseTLV($signedDataTlv['value'], $sdValueOffset);
            if ($child === null) {
                break;
            }
            $sdChildren[] = ['tag' => $child['tag'], 'length' => $child['length'],
                             'value' => $child['value'], 'raw_start' => $childStart,
                             'raw_len' => $sdValueOffset - $childStart];
        }

        if (count($sdChildren) < 3) {
            return null;
        }

        // encapContentInfo es sdChildren[2]
        $encapRaw = $sdChildren[2]['value'];
        $encapOff = 0;

        // contentType OID
        $encapOidTlv = $this->parseTLV($encapRaw, $encapOff);
        if ($encapOidTlv === null) {
            return null;
        }

        // eContent [0] EXPLICIT
        $eContentA0 = $this->parseTLV($encapRaw, $encapOff);
        if ($eContentA0 === null || $eContentA0['tag'] !== 0xa0) {
            return null;
        }

        // OCTET STRING dentro del A0
        $osOff = 0;
        $octetString = $this->parseTLV($eContentA0['value'], $osOff);
        if ($octetString === null) {
            // [0] IMPLICIT: el valor ES el TSTInfo directo
            $tstInfoDer = $eContentA0['value'];
        } else {
            $tstInfoDer = $octetString['value'];
        }

        // TSTInfo ::= SEQUENCE { version INTEGER, policy OID,
        //   messageImprint SEQUENCE, serialNumber INTEGER,
        //   genTime GeneralizedTime, accuracy SEQUENCE OPTIONAL, ... }
        $tstOff = 0;
        $tstInfo = $this->parseSequence($tstInfoDer, $tstOff);
        if ($tstInfo === null) {
            return null;
        }

        // Parsear hijos de TSTInfo (superficie pequeña, sin riesgo)
        $tstChildren = [];
        $tstValOff = 0;
        while ($tstValOff < strlen($tstInfo['value'])) {
            $c = $this->parseTLV($tstInfo['value'], $tstValOff);
            if ($c === null) {
                break;
            }
            $tstChildren[] = $c;
        }

        if (count($tstChildren) < 5) {
            return null;
        }

        // genTime en posición 4, messageImprint en posición 2
        $genTime = $this->parseGeneralizedTime($tstChildren[4]['value'] ?? '');

        $miOff = 0;
        $miSeq = $this->parseSequence($tstChildren[2]['value'] ?? '', $miOff);
        $messageImprint = null;
        if ($miSeq !== null) {
            $miChildren = [];
            $miChildOff = 0;
            while ($miChildOff < strlen($miSeq['value'])) {
                $mc = $this->parseTLV($miSeq['value'], $miChildOff);
                if ($mc === null) break;
                $miChildren[] = $mc;
            }
            if (count($miChildren) >= 2) {
                $messageImprint = bin2hex($miChildren[1]['value'] ?? '');
            }
        }

        // Certificados: sdChildren[3] con tag [0] IMPLICIT (0xa0)
        // Solo extraer los cert DER sin parseo recursivo profundo.
        $certificados = [];
        if (isset($sdChildren[3]) && $sdChildren[3]['tag'] === 0xa0) {
            $certsDer = $sdChildren[3]['value'];
            $certsOff = 0;
            while ($certsOff < strlen($certsDer)) {
                $certStart = $certsOff;
                $certSeq = $this->parseTLV($certsDer, $certsOff);
                if ($certSeq === null || $certSeq['tag'] !== 0x30) {
                    break;
                }
                $certDer = substr($certsDer, $certStart, $certsOff - $certStart);
                $pem = '-----BEGIN CERTIFICATE-----'."\n"
                    .chunk_split(base64_encode($certDer), 64, "\n")
                    .'-----END CERTIFICATE-----';
                $certificados[] = ['der' => $pem, 'info' => null];
            }
        }

        // signerInfos: último hijo de SignedData (SET tag 0x31)
        $lastIdx = count($sdChildren) - 1;
        $signerInfosRaw = '';
        if (isset($sdChildren[$lastIdx]) && $sdChildren[$lastIdx]['tag'] === 0x31) {
            $signerInfosRaw = $sdChildren[$lastIdx]['value'];
        }

        return [
            'fecha' => $genTime,
            'message_imprint' => $messageImprint,
            'certificados' => $certificados,
            'signed_data' => $a0Tlv['value'],
            'tst_info' => $tstInfoDer,
            'signer_infos_raw' => $signerInfosRaw,
        ];
    }

    /**
     * Verifica la firma del token usando el certificado TSA del token.
     *
     * @param  string  $tokenDer
     * @param  array{fecha:?string, message_imprint:?string, certificados:array, signed_data:string, tst_info:string, signer_infos_raw:string}  $parsed
     * @return bool
     */
    private function verificarFirmaToken(string $tokenDer, array $parsed): bool
    {
        if (empty($parsed['certificados'])) {
            Log::warning('No se encontraron certificados en el token TSA');

            return false;
        }

        $certPem = $parsed['certificados'][0]['der'] ?? null;
        if ($certPem === null) {
            return false;
        }

        $cert = openssl_x509_read($certPem);
        if ($cert === false) {
            return false;
        }

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

        // Parsear SignerInfo desde signerInfos_raw (SET OF SignerInfo)
        $siSetOff = 0;
        $siSet = $this->parseSet($parsed['signer_infos_raw'], $siSetOff);
        if ($siSet === null) {
            return false;
        }

        // Primer SignerInfo
        $siOff = 0;
        $signerInfo = $this->parseSequence($siSet['value'], $siOff);
        if ($signerInfo === null) {
            return false;
        }

        // Extraer signedAttrs y signature del SignerInfo
        $siValueOff = 0;
        $signedAttrsRaw = null;
        $signatureValue = null;
        $digestAlgorithm = 'SHA256';

        while ($siValueOff < strlen($signerInfo['value'])) {
            $child = $this->parseTLV($signerInfo['value'], $siValueOff);
            if ($child === null) {
                break;
            }

            if ($child['tag'] === 0x30 && $digestAlgorithm === 'SHA256') {
                // Posible digestAlgorithm (primera SEQUENCE)
                $algOff = 0;
                $algSeq = $this->parseSequence($child['value'], $algOff);
                if ($algSeq !== null) {
                    $algChildren = [];
                    $algChildOff = 0;
                    while ($algChildOff < strlen($algSeq['value'])) {
                        $ac = $this->parseTLV($algSeq['value'], $algChildOff);
                        if ($ac === null) break;
                        $algChildren[] = $ac;
                    }
                    if (!empty($algChildren)) {
                        $oid = $this->parseOID($algChildren[0]['value']);
                        $digestAlgorithm = match ($oid) {
                            '2.16.840.1.101.3.4.2.1' => 'SHA256',
                            '1.3.14.3.2.26' => 'SHA1',
                            default => 'SHA256',
                        };
                    }
                }
            } elseif ($child['tag'] === 0xa0) {
                // signedAttrs — la firma cubre el TLV completo (tag + len + value)
                $signedAttrsRaw = chr(0xa0).$this->encodeLength(strlen($child['value'])).$child['value'];
            } elseif ($child['tag'] === 0x04) {
                $signatureValue = $child['value'];
            }
        }

        if ($signatureValue === null) {
            Log::warning('No se pudo extraer la firma del token TSA');

            return false;
        }

        $dataToVerify = $signedAttrsRaw ?? $parsed['tst_info'];

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

    private function encodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        $tmp = $length;
        while ($tmp > 0) {
            $bytes = chr($tmp & 0xFF).$bytes;
            $tmp >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

PHP;

$c = substr_replace($c, $nuevo, $start, $end - $start);
file_put_contents($file, $c);
echo 'REEMPLAZADO OK'.PHP_EOL;
