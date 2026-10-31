<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

/**
 * Rechaza el PDF protegido (cifrado o con restricciones): el sello de la firma electrónica no se
 * puede estampar sobre él. Los demás tipos de archivo y los PDF que FPDI no lee por otros motivos
 * pasan; esta regla solo se ocupa del cifrado.
 */
class PdfFirmable implements ValidationRule
{
    public const MENSAJE = 'El PDF está protegido: súbalo sin contraseña ni restricciones para poder firmarlo.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || strtolower($value->getClientOriginalExtension()) !== 'pdf') {
            return;
        }

        if (self::estaCifrado($value->getRealPath() ?: $value->getPathname())) {
            $fail(self::MENSAJE);
        }
    }

    public static function estaCifrado(string $path): bool
    {
        try {
            (new Fpdi)->setSourceFile($path);
        } catch (CrossReferenceException $e) {
            return $e->getCode() === CrossReferenceException::ENCRYPTED;
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
