<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigServerArchivoRequest extends FormRequest
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
            'nombre' => [
                'sometimes',
                'string',
                'max:100'
            ],
            'url' => [
                'sometimes',
                'url',
                'max:255'
            ],
            'puerto' => [
                'sometimes',
                'integer',
                'min:1',
                'max:65535'
            ],
            'usuario' => [
                'sometimes',
                'string',
                'max:50'
            ],
            'password' => [
                'sometimes',
                'string',
                'max:100'
            ],
            'ruta_base' => [
                'sometimes',
                'string',
                'max:255'
            ],
            'proceso_id' => [
                'nullable',
                'integer',
                'exists:procesos,id'
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
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
            'url.url' => 'La URL debe tener un formato válido.',
            'url.max' => 'La URL no puede superar los 255 caracteres.',
            'puerto.integer' => 'El puerto debe ser un número entero.',
            'puerto.min' => 'El puerto debe ser al menos 1.',
            'puerto.max' => 'El puerto no puede exceder 65535.',
            'usuario.string' => 'El usuario debe ser una cadena de texto.',
            'usuario.max' => 'El usuario no puede superar los 50 caracteres.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.max' => 'La contraseña no puede superar los 100 caracteres.',
            'ruta_base.string' => 'La ruta base debe ser una cadena de texto.',
            'ruta_base.max' => 'La ruta base no puede superar los 255 caracteres.',
            'proceso_id.integer' => 'El ID del proceso debe ser un número entero.',
            'proceso_id.exists' => 'El proceso seleccionado no existe.',
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
            'nombre' => 'nombre del servidor',
            'url' => 'URL',
            'puerto' => 'puerto',
            'usuario' => 'usuario',
            'password' => 'contraseña',
            'ruta_base' => 'ruta base',
            'proceso_id' => 'proceso',
            'estado' => 'estado'
        ];
    }
}
