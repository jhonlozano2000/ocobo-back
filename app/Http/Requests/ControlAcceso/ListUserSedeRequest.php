<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class ListUserSedeRequest extends FormRequest
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
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'sede_id' => [
                'nullable',
                'integer',
                'exists:config_sedes,id'
            ],
            'estado' => [
                'nullable',
                'in:0,1'
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
            'user_id.integer' => 'El ID del usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'sede_id.integer' => 'El ID de la sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'estado.in' => 'El estado debe ser 0 o 1.',
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
            'user_id' => 'usuario',
            'sede_id' => 'sede',
            'estado' => 'estado',
            'per_page' => 'elementos por página'
        ];
    }
}
