<?php

namespace App\Rules;

use App\Http\Middleware\AccesoRadicado;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que el radicado exista y que el usuario actual tenga acceso a él (ABAC).
 * Responde igual en ambos casos para no revelar radicados ajenos.
 *
 * @param  int|null  $actual  id ya vinculado al recurso: conservarlo no exige acceso de nuevo
 */
class RadicadoAccesible implements ValidationRule
{
    public function __construct(private string $tipo = 'recibido', private ?int $actual = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->actual !== null && (int) $value === $this->actual) {
            return;
        }

        $user = auth()->user();

        if (! $user || ! is_numeric($value) || ! AccesoRadicado::permitido($user, $this->tipo, (int) $value)) {
            $fail('El radicado seleccionado no existe.');
        }
    }
}
