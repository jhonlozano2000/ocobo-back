<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B25: filtros del listado de expedientes.
 */
class ListarExpedientesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => 'nullable|in:abierto,cerrado,transferido,eliminado',
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'El estado debe ser abierto, cerrado, transferido o eliminado.',
        ];
    }
}
