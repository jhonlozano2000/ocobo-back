<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigListaDetalleRequest extends FormRequest
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
            'lista_id' => [
                'sometimes',
                'integer',
                'exists:config_listas,id'
            ],
            'codigo' => [
                'sometimes',
                'string',
                'max:20'
            ],
            'nombre' => [
                'sometimes',
                'string',
                'max:100'
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255'
            ],
            'orden' => [
                'nullable',
                'integer',
                'min:1'
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
            'lista_id.integer' => 'El ID de la lista debe ser un número entero.',
            'lista_id.exists' => 'La lista seleccionada no existe.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede superar los 20 caracteres.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden debe ser al menos 1.',
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
            'lista_id' => 'lista',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'orden' => 'orden',
            'estado' => 'estado'
        ];
    }
}
