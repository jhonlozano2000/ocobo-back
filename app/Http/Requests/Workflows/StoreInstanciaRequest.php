<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;

/** M19-30: iniciar una instancia, opcionalmente desde un radicado recibido o una PQRS. */
class StoreInstanciaRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    public function rules(): array
    {
        return [
            'origen_tipo' => 'nullable|string|in:radicado_recibido,pqrs|required_with:origen_id',
            'origen_id' => 'nullable|integer|min:1|required_with:origen_tipo',
        ];
    }

    public function messages(): array
    {
        return [
            'origen_tipo.in' => 'Un flujo solo puede iniciarse desde un radicado recibido o una PQRS',
            'origen_tipo.required_with' => 'Indique si el origen es un radicado recibido o una PQRS',
            'origen_id.required_with' => 'Indique el radicado o la PQRS de origen',
        ];
    }
}
