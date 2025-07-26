<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigVariasRequest extends FormRequest
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
            'valor' => [
                'required',
                'string',
                'max:255'
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:500'
            ],
            'tipo' => [
                'nullable',
                'string',
                'max:50'
            ],
            'estado' => [
                'nullable',
                'in:0,1,true,false'
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
            'valor.required' => 'El valor es obligatorio.',
            'valor.string' => 'El valor debe ser una cadena de texto.',
            'valor.max' => 'El valor no puede superar los 255 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'tipo.string' => 'El tipo debe ser una cadena de texto.',
            'tipo.max' => 'El tipo no puede superar los 50 caracteres.',
            'estado.in' => 'El estado debe ser 0, 1, true o false.',
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
            'valor' => 'valor',
            'descripcion' => 'descripción',
            'tipo' => 'tipo',
            'estado' => 'estado'
        ];
    }
}
