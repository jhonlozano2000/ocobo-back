<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserSedeRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id'
            ],
            'sede_id' => [
                'required',
                'integer',
                'exists:config_sedes,id'
            ],
            'estado' => [
                'nullable',
                'in:0,1,true,false'
            ],
            'observaciones' => [
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
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.integer' => 'El ID del usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'El ID de la sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
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
            'user_id' => 'usuario',
            'sede_id' => 'sede',
            'estado' => 'estado',
            'observaciones' => 'observaciones'
        ];
    }
}
