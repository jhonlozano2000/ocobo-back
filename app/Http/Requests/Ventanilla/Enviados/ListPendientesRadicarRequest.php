<?php

namespace App\Http\Requests\Ventanilla\Enviados;

use Illuminate\Foundation\Http\FormRequest;

class ListPendientesRadicarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'fecha_desde.date' => 'La fecha desde debe tener un formato válido.',
            'fecha_hasta.date' => 'La fecha hasta debe tener un formato válido.',
            'per_page.max' => 'El número de elementos por página no puede superar 100.',
        ];
    }
}
