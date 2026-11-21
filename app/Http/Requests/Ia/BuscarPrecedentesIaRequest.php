<?php

namespace App\Http\Requests\Ia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuscarPrecedentesIaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la búsqueda de precedentes RAG.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consulta' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
            'top_k' => [
                'nullable',
                'integer',
                'min:1',
                'max:20',
            ],
            'flag_hc' => [
                'nullable',
                'boolean',
            ],
            'flag_tutela' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'consulta.required' => 'El texto o criterio de consulta es obligatorio.',
            'consulta.string' => 'La consulta debe ser una cadena de texto.',
            'consulta.min' => 'La consulta debe tener al menos 3 caracteres.',
            'consulta.max' => 'La consulta no puede superar los 1000 caracteres.',
            'top_k.integer' => 'El número de resultados (top_k) debe ser un número entero.',
            'top_k.min' => 'El número de resultados debe ser al menos 1.',
            'top_k.max' => 'El número de resultados no puede ser mayor a 20.',
            'flag_hc.boolean' => 'El indicador de historia clínica debe ser verdadero o falso.',
            'flag_tutela.boolean' => 'El indicador de tutela debe ser verdadero o falso.',
        ];
    }

    /**
     * Atributos personalizados para los campos de validación.
     */
    public function attributes(): array
    {
        return [
            'consulta' => 'criterio de consulta',
            'top_k' => 'cantidad de precedentes',
            'flag_hc' => 'indicador de historia clínica',
            'flag_tutela' => 'indicador de tutela',
        ];
    }
}
