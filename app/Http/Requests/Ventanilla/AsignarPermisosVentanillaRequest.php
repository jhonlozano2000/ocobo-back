<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class AsignarPermisosVentanillaRequest extends FormRequest
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
            'usuarios' => [
                'required',
                'array',
                'min:1'
            ],
            'usuarios.*' => [
                'required',
                'integer',
                'exists:users,id'
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
            'usuarios.required' => 'Los usuarios son obligatorios.',
            'usuarios.array' => 'Los usuarios deben ser un arreglo.',
            'usuarios.min' => 'Debe seleccionar al menos un usuario.',
            'usuarios.*.required' => 'Cada usuario es obligatorio.',
            'usuarios.*.integer' => 'Cada usuario debe ser un número entero.',
            'usuarios.*.exists' => 'Uno o más usuarios no existen.'
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
            'usuarios' => 'usuarios',
            'usuarios.*' => 'usuario'
        ];
    }
}
