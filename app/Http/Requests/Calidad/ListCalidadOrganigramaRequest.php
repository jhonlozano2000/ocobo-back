<?php

namespace App\Http\Requests\Calidad;

use Illuminate\Foundation\Http\FormRequest;

class ListCalidadOrganigramaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // La autorización se maneja a través de middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => [
                'nullable',
                'string',
                'in:Dependencia,Oficina,Cargo'
            ],
            'search' => [
                'nullable',
                'string',
                'max:100'
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser Dependencia, Oficina o Cargo.',
            'search.string' => 'El término de búsqueda debe ser una cadena de texto.',
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'per_page.integer' => 'El número de elementos por página debe ser un número entero.',
            'per_page.min' => 'El número de elementos por página debe ser al menos 1.',
            'per_page.max' => 'El número de elementos por página no puede superar 100.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo',
            'search' => 'término de búsqueda',
            'per_page' => 'elementos por página'
        ];
    }
}
