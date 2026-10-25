<?php

namespace App\Rules;

use App\Services\Seguridad\InspectorArchivos;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rechaza el archivo subido que no se puede leer (p. ej. el antivirus del servidor lo bloqueó o lo
 * borró). Va con bail, justo después de 'file': las reglas max y mimes leen el archivo y, si no
 * pueden, lanzan una excepción (500) en lugar de devolver un error de validación.
 */
class ArchivoLegible implements ValidationRule
{
    public const MENSAJE = 'El archivo está vacío o no se pudo leer.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Lo que no es un archivo subido lo rechaza la regla 'file'
        if (! $value instanceof UploadedFile) {
            return;
        }

        $path = $value->getRealPath() ?: $value->getPathname();

        if (! is_file($path) || ! is_readable($path) || InspectorArchivos::mimeReal($path) === '') {
            $fail(self::MENSAJE);
        }
    }
}
