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
            'search' => 'nullable|string|max:100',
            'dependencia_id' => 'nullable|integer',
            'serie_trd_id' => 'nullable|integer',
            'fase' => 'nullable|string|max:30',
            'por_completar' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'El estado debe ser abierto, cerrado, transferido o eliminado.',
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
        ];
    }
}
