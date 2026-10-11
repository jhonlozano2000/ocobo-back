<?php

namespace App\Rules;

use App\Services\Seguridad\InspectorArchivos;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Valida el contenido real del archivo subido con InspectorArchivos
 * (extensión permitida, contenido ejecutable, contenido vs. extensión, zip-bomb).
 */
class ArchivoSeguro implements ValidationRule
{
    /**
     * @param  array|null  $extensiones  null = extensiones de ConfigVarias tipos_archivos_permitidos
     */
    public function __construct(private ?array $extensiones = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('El archivo no es válido.');

            return;
        }

        $error = InspectorArchivos::inspeccionar(
            $value->getRealPath() ?: $value->getPathname(),
            $value->getClientOriginalName(),
            $this->extensiones ?? InspectorArchivos::extensionesConfiguradas()
        );

        if ($error !== null) {
            Log::warning('Archivo rechazado por inspección de contenido', [
                'campo' => $attribute,
                'nombre' => $value->getClientOriginalName(),
                'motivo' => $error,
                'user_id' => auth()->id(),
            ]);

            $fail($error);
        }
    }
}
