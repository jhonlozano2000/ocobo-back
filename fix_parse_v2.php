<?php
$file = __DIR__.'/app/Services/Firma/TsaService.php';
$c = file_get_contents($file);

// Buscar límites
$start = strpos($c, '    private function parseTimeStampToken');
$vftPos = strpos($c, 'Verifica la firma del token usando');
if ($vftPos === false) { die("No encontré verificarFirmaToken doc\n"); }
// Retroceder hasta el /** antes de verificarFirmaToken
$end = strrpos(substr($c, 0, $vftPos), '/**');
echo "start=$start end=$end\n";

$nuevo = <<<'PHPCODE'
    private function parseTimeStampToken(string $tokenDer): ?array
    {
        // Navegación por offsets — sin recursión profunda en certificados.
        $offset = 0;

        // ContentInfo ::= SEQUENCE { contentType OID, content [0] EXPLICIT }
        $ciTag = ord($tokenDer[0] ?? "\x00");
        if ($ciTag !== 0x30) {
            return null;
        }
        $ciOffset = 1;
        $ciLenByte = ord($tokenDer[$ciOffset]);
        $ciOffset++;
        if ($ciLenByte < 0x80) {
            $ciLen = $ciLenByte;
        } else {
            $nBytes = $ciLenByte & 0x7F;
            $ciLen = 0;
            for ($i = 0; $i < $nBytes; $i++) {
                $ciLen = ($ciLen << 8) | ord($tokenDer[$ciOffset]);
                $ciOffset++;
            }
        }
        $ciValue = substr($tokenDer, $ciOffset, $ciLen);

        // Dentro del ContentInfo: OID (contentType), luego [0] EXPLICIT (SignedData)
        $innerOffset = 0;

        // OID contentType
        $oidTag = ord($ciValue[$innerOffset] ?? "\x00");
        if ($oidTag !== 0x06) {
            return null;
        }
        $oidLenByte = ord($ciValue[$innerOffset + 1]);
        $innerOffset += 2;
        $oidLen = $oidLenByte < 0x80 ? $oidLenByte : (($oidLenByte & 0x7F) > 0 ? $this->readMultiByteLength($ciValue, $innerOffset, $oidLenByte & 0x7F) : 0);
        $oidValue = substr($ciValue, $innerOffset, $oidLen);
        $innerOffset += $oidLen;

        if ($this->parseOID($oidValue) !== '1.2.840.113549.1.7.2') {
            return null;
        }

        // [0] EXPLICIT → contiene SignedData SEQUENCE
        $a0Tag = ord($ciValue[$innerOffset] ?? "\x00");
        if ($a0Tag !== 0xa0) {
            return null;
        }
        $innerOffset++;
        $a0LenByte = ord($ciValue[$innerOffset]);
        $innerOffset++;
        if ($a0LenByte < 0x80) {
            $a0Len = $a0LenByte;
        } else {
            $nBytes = $a0LenByte & 0x7F;
            $a0Len = 0;
            for ($i = 0; $i < $nBytes; $i++) {
                $a0Len = ($a0Len << 8) | ord($ciValue[$innerOffset]);
                $innerOffset++;
            }
        }

        // SignedData content
        $sdData = substr($ciValue, $innerOffset, $a0Len);

        // Navegar SignedData children por offset
        $sdOff = 0;
        $sdVersionTlv = $this->readTLV($sdData, $sdOff);           // version INTEGER
        $digestAlgosTlv = $this->readTLV($sdData, $sdOff);          // digestAlgorithms SET
        $encapTlv = $this->readTLV($sdData, $sdOff);               // encapContentInfo SEQUENCE

        // Saltar certificates [0] si existe (sin parsear contenido)
        $certsStart = $sdOff;
        $nextPeek = ord($sdData[$sdOff] ?? "\x00");
        $certificatesRaw = '';
        if ($nextPeek === 0xa0) {
            $certsTlv = $this->readTLV($sdData, $sdOff);
            $certificatesRaw = $certsTlv['value'] ?? '';
        }

        // signerInfos SET
        $signerInfosTlv = $this->readTLV($sdData, $sdOff);
        if ($signerInfosTlv === null || $signerInfosTlv['tag'] !== 0x31) {
            return null;
        }

        // ── encapContentInfo: extraer TSTInfo DER ──
        $encapValOff = 0;
        $encapOid = $this->readTLV($encapTlv['value'], $encapValOff);
        $eContentA0 = $this->readTLV($encapTlv['value'], $encapValOff);
        if ($eContentA0 === null || $eContentA0['tag'] !== 0xa0) {
            return null;
        }

        $osOff = 0;
        $octetStr = $this->readTLV($eContentA0['value'], $osOff);
        $tstInfoDer = $octetStr !== null ? $octetStr['value'] : $eContentA0['value'];

        // ── Parsear TSTInfo para genTime y messageImprint ──
        $tstOff = 0;
        $tstChildren = [];
        while ($tstOff < strlen($tstInfoDer)) {
            $c = $this->readTLV($tstInfoDer, $tstOff);
            if ($c === null) break;
            $tstChildren[] = $c;
        }

        if (count($tstChildren) < 5) {
            return null;
        }

        // genTime (posición 4)
        $genTimeRaw = $tstChildren[4]['value'] ?? '';
        $genTime = $this->parseGeneralizedTime($genTimeRaw);

        // messageImprint (posición 2): SEQUENCE { alg OID, hash OCTET STRING }
        $miOff = 0;
        $miChildren = [];
        while ($miOff < strlen($tstChildren[2]['value'])) {
            $mc = $this->readTLV($tstChildren[2]['value'], $miOff);
            if ($mc === null) break;
            $miChildren[] = $mc;
        }
        $messageImprint = count($miChildren) >= 2 ? bin2hex($miChildren[1]['value']) : null;

        // ── Certificados: iterar sin recursión ──
        $certificados = [];
        $certsOff = 0;
        while ($certsOff < strlen($certificatesRaw)) {
            $certStart = $certsOff;
            $certSeq = $this->readTLV($certificatesRaw, $certsOff);
            if ($certSeq === null || $certSeq['tag'] !== 0x30) break;
            $certDer = substr($certificatesRaw, $certStart, $certsOff - $certStart);
            $certificados[] = [
                'der' => "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($certDer), 64, "\n")."-----END CERTIFICATE-----",
                'info' => null,
            ];
        }

        // ── SignerInfos raw para verificación de firma posterior ──
        $signerInfosRaw = $signerInfosTlv['value'];

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
     * Lee un TLV del buffer y avanza el offset. Sin recursión ni validación de tag.
     */
    private function readTLV(string $der, int &$offset): ?array
    {
        if ($offset >= strlen($der)) {
            return null;
        }
        $tag = ord($der[$offset]);
        $offset++;

        if ($offset >= strlen($der)) {
            return null;
        }

        $lb = ord($der[$offset]);
        $offset++;

        if ($lb < 0x80) {
            $len = $lb;
        } elseif ($lb === 0x80) {
            return null; // longitud indefinida no soportada
        } else {
            $n = $lb & 0x7F;
            if ($offset + $n > strlen($der)) {
                return null;
            }
            $len = 0;
            for ($i = 0; $i < $n; $i++) {
                $len = ($len << 8) | ord($der[$offset]);
                $offset++;
            }
        }

        if ($offset + $len > strlen($der)) {
            return null;
        }

        $value = substr($der, $offset, $len);
        $offset += $len;

        return ['tag' => $tag, 'length' => $len, 'value' => $value];
    }

    private function readMultiByteLength(string $der, int &$offset, int $numBytes): int
    {
        $len = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $len = ($len << 8) | ord($der[$offset]);
            $offset++;
        }
        return $len;
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
        if ($now < ($certInfo['validFrom_time_t'] ?? 0) || $now > ($certInfo['validTo_time_t'] ?? 0)) {
            Log::warning('Certificado TSA fuera de vigencia');

            return false;
        }

        // Extraer signedAttrs y signature del signerInfos raw
        $siSetOff = 0;
        $siSet = $this->readTLV($parsed['signer_infos_raw'], $siSetOff);
        if ($siSet === null) {
            return false;
        }

        $siOff = 0;
        $signerInfo = $this->readTLV($siSet['value'], $siOff);
        if ($signerInfo === null) {
            return false;
        }

        $siInnerOff = 0;
        $signedAttrsFull = null;
        $signatureValue = null;
        $digestAlgorithm = 'SHA256';
        $firstSequenceSeen = false;

        while ($siInnerOff < strlen($signerInfo['value'])) {
            $child = $this->readTLV($signerInfo['value'], $siInnerOff);
            if ($child === null) {
                break;
            }

            if ($child['tag'] === 0x30 && ! $firstSequenceSeen) {
                $firstSequenceSeen = true;
                // Es el digestAlgorithm
                $algOff = 0;
                $algOid = $this->readTLV($child['value'], $algOff);
                if ($algOid !== null && $algOid['tag'] === 0x06) {
                    $oidStr = $this->parseOID($algOid['value']);
                    $digestAlgorithm = match ($oidStr) {
                        '2.16.840.1.101.3.4.2.1' => 'SHA256',
                        '1.3.14.3.2.26' => 'SHA1',
                        default => 'SHA256',
                    };
                }
            } elseif ($child['tag'] === 0xa0) {
                // signedAttrs: la firma cubre tag+length+value completo
                $signedAttrsFull = chr(0xa0).$this->encodeLength(strlen($child['value'])).$child['value'];
            } elseif ($child['tag'] === 0x04) {
                $signatureValue = $child['value'];
            }
        }

        if ($signatureValue === null) {
            Log::warning('No se pudo extraer la firma del token TSA');

            return false;
        }

        $dataToVerify = $signedAttrsFull ?? $parsed['tst_info'];

        $openAlgo = match ($digestAlgorithm) {
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
     * Codifica la longitud TLV.
     */
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

PHPCODE;

$c = substr_replace($c, $nuevo.PHP_EOL, $start, $end - $start);
file_put_contents($file, $c);
echo "REEMPLAZADO OK\n";
