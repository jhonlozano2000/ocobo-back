---
name: ocobo-form-requests
description: Define el estándar del proyecto OCOBO-BACK para crear Form Requests (ubicación por módulo, rules/messages/attributes, normalización de JSON en prepareForValidation y failedValidation con respuesta JSON uniforme). Usar cuando se creen o editen archivos en app/Http/Requests/.
---

# OCOBO Form Requests

## Objetivo

Validación consistente y respuestas 422 uniformes en toda la API.

## Ubicación y nombres

- Ubicación: `app/Http/Requests/{Modulo}/`
- Nombre: `StoreXRequest`, `UpdateXRequest`, `ListXRequest`, etc. (PascalCase + sufijo `Request`)

## Estructura mínima obligatoria

- `authorize()` retornando `true` (la autorización se resuelve por middleware/permisos).
- `rules(): array`
- `messages(): array`
- `attributes(): array`
- `failedValidation()` con el formato JSON del proyecto:

```json
{ "status": false, "message": "Errores de validación.", "errors": { ... } }
```

## Plantilla recomendada

```php
<?php

namespace App\Http\Requests\Modulo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class StoreRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'campo' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            // 'campo.required' => '...',
        ];
    }

    public function attributes(): array
    {
        return [
            // 'campo' => 'campo legible',
        ];
    }

    /**
     * Si el front envía JSON “crudo” y llega vacío, mergearlo antes de validar.
     * Usar este patrón cuando ya exista el mismo caso en el módulo.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (empty($input) && $this->getContent()) {
            $jsonData = json_decode($this->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $this->merge($jsonData);
            }
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json([
            'status'  => false,
            'message' => 'Errores de validación.',
            'errors'  => $validator->errors(),
        ], 422);

        throw new ValidationException($validator, $response);
    }
}
```

## Reglas prácticas del proyecto

- Preferir reglas en array cuando haya `Rule::unique(...)`, closures, o validaciones condicionales.
- Mantener mensajes en español (ya es el estándar del repo).

