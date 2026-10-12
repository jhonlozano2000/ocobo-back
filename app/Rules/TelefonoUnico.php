<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Valida que un número de teléfono no esté registrado en otra fila de la tabla.
 * Compara solo los dígitos, así "300 123-4567" y "3001234567" cuentan como el mismo número,
 * y revisa todas las columnas indicadas (p. ej. `tel` y `movil` de users).
 *
 * @param  array<int, string>  $columnas  columnas de teléfono donde buscar
 * @param  Model|int|string|null  $ignorar  registro que se está editando
 */
class TelefonoUnico implements ValidationRule
{
    public function __construct(
        private string $tabla,
        private array $columnas,
        private Model|int|string|null $ignorar = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = preg_replace('/\D/', '', (string) $value);

        if ($digitos === '') {
            return;
        }

        $ignorar = $this->ignorar instanceof Model ? $this->ignorar->getKey() : $this->ignorar;

        $existe = DB::table($this->tabla)
            ->when($ignorar !== null, fn ($q) => $q->where('id', '!=', $ignorar))
            ->where(function ($q) use ($digitos) {
                foreach ($this->columnas as $columna) {
                    $q->orWhereRaw("REGEXP_REPLACE({$columna}, '[^0-9]', '') = ?", [$digitos]);
                }
            })
            ->exists();

        if ($existe) {
            $fail('El :attribute ya está registrado.');
        }
    }
}
