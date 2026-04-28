<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;

/**
 * Helper de Cifrado de Datos
 *
 * OWASP A02: Cryptographic Failures
 *
 * Proporciona métodos para cifrar/descifrar datos sensibles.
 */
class DataEncryption
{
    /**
     * Cifra datos sensibles
     *
     * @param mixed $data
     * @return string
     */
    public static function encrypt($data): string
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        return Crypt::encryptString($data);
    }

    /**
     * Descifra datos
     *
     * @param string $encrypted
     * @param bool $asArray
     * @return mixed
     */
    public static function decrypt(string $encrypted, bool $asArray = false)
    {
        $decrypted = Crypt::decryptString($encrypted);

        if ($asArray) {
            return json_decode($decrypted, true);
        }

        return $decrypted;
    }

    /**
     * Cifra datos PII para almacenamiento
     *
     * @param array $piiData
     * @return array
     */
    public static function encryptPii(array $piiData): array
    {
        $fields = ['num_docu', 'email', 'telefono', 'movil', 'direccion'];

        foreach ($fields as $field) {
            if (isset($piiData[$field]) && !empty($piiData[$field])) {
                $piiData[$field] = self::encrypt($piiData[$field]);
            }
        }

        return $piiData;
    }

    /**
     * Descifra datos PII
     *
     * @param array $encryptedData
     * @return array
     */
    public static function decryptPii(array $encryptedData): array
    {
        $fields = ['num_docu', 'email', 'telefono', 'movil', 'direccion'];

        foreach ($fields as $field) {
            if (isset($encryptedData[$field]) && !empty($encryptedData[$field])) {
                $encryptedData[$field] = self::decrypt($encryptedData[$field], true);
            }
        }

        return $encryptedData;
    }

    /**
     * Hash de datos para integridad
     *
     * @param mixed $data
     * @return string
     */
    public static function hash($data): string
    {
        if (is_array($data)) {
            $data = json_encode($data, JSONSortKeys);
        }

        return hash('sha256', $data);
    }

    /**
     * Verifica integridad de datos
     *
     * @param mixed $data
     * @param string $expectedHash
     * @return bool
     */
    public static function verify($data, string $expectedHash): bool
    {
        return hash_equals(self::hash($data), $expectedHash);
    }

    /**
     * Genera token seguro
     *
     * @param int $length
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}