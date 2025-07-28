<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigVariasRequest extends FormRequest
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
            'clave' => [
                'required',
                'string',
                'max:100',
                'unique:config_varias,clave'
            ],
            'valor' => [
                'required',
                'string',
                'max:255'
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255'
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
            'clave.required' => 'La clave de configuración es obligatoria.',
            'clave.string' => 'La clave debe ser una cadena de texto.',
            'clave.max' => 'La clave no puede superar los 100 caracteres.',
            'clave.unique' => 'La clave de configuración ya existe.',
            'valor.required' => 'El valor de configuración es obligatorio.',
            'valor.string' => 'El valor debe ser una cadena de texto.',
            'valor.max' => 'El valor no puede superar los 255 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.'
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
            'clave' => 'clave de configuración',
            'valor' => 'valor de configuración',
            'descripcion' => 'descripción'
        ];
    }
}
