<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSedeRequest extends FormRequest
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
            'estado' => [
                'sometimes',
                'in:0,1,true,false'
            ],
            'observaciones' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000'
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
            'estado.in' => 'El estado debe ser 0, 1, true o false.',
            'observaciones.string' => 'Las observaciones deben ser una cadena de texto.',
            'observaciones.max' => 'Las observaciones no pueden superar los 1000 caracteres.'
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
            'estado' => 'estado',
            'observaciones' => 'observaciones'
        ];
    }
}
