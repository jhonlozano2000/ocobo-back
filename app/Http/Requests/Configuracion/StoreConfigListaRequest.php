<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigListaRequest extends FormRequest
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
            'cod' => [
                'required',
                'string',
                'max:10',
                'unique:config_listas,cod'
            ],
            'nombre' => [
                'required',
                'string',
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
            'cod.required' => 'El código es obligatorio.',
            'cod.string' => 'El código debe ser una cadena de texto.',
            'cod.max' => 'El código no puede tener más de 10 caracteres.',
            'cod.unique' => 'El código ya está en uso, por favor elija otro.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
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
            'cod' => 'código',
            'nombre' => 'nombre'
        ];
    }
}
