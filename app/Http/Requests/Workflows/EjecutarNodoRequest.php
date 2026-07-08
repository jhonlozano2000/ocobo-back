<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

class EjecutarNodoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    public function rules(): array
    {
        return [
            'nodo_id' => 'required|exists:workflow_nodos,id',
            'resultado' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'nodo_id.required' => 'El nodo a ejecutar es obligatorio',
            'nodo_id.exists' => 'El nodo especificado no existe',
        ];
    }
}
