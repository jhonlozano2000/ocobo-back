<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigServerArchivoRequest extends FormRequest
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
            'proceso_id' => [
                'required',
                'integer',
                'exists:config_listas_detalles,id'
            ],
            'host' => [
                'required',
                'string',
                'max:15'
            ],
            'ruta' => [
                'nullable',
                'string',
                'max:100'
            ],
            'user' => [
                'required',
                'string',
                'max:20'
            ],
            'password' => [
                'required',
                'string'
            ],
            'detalle' => [
                'nullable',
                'string',
                'max:200'
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
            'proceso_id.required' => 'El proceso es obligatorio.',
            'proceso_id.integer' => 'El ID del proceso debe ser un número entero.',
            'proceso_id.exists' => 'El proceso seleccionado no existe.',
            'host.required' => 'El host es obligatorio.',
            'host.string' => 'El host debe ser una cadena de texto.',
            'host.max' => 'El host no puede superar los 15 caracteres.',
            'ruta.string' => 'La ruta debe ser una cadena de texto.',
            'ruta.max' => 'La ruta no puede superar los 100 caracteres.',
            'user.required' => 'El usuario es obligatorio.',
            'user.string' => 'El usuario debe ser una cadena de texto.',
            'user.max' => 'El usuario no puede superar los 20 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'detalle.string' => 'El detalle debe ser una cadena de texto.',
            'detalle.max' => 'El detalle no puede superar los 200 caracteres.',
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
            'proceso_id' => 'proceso',
            'host' => 'host',
            'ruta' => 'ruta',
            'user' => 'usuario',
            'password' => 'contraseña',
            'detalle' => 'detalle',
            'estado' => 'estado'
        ];
    }
}
