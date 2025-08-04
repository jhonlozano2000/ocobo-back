<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigListaDetalleRequest extends FormRequest
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
                'required',
                'integer',
                'exists:config_listas,id'
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:20'
            ],
            'nombre' => [
                'required',
                'string',
                'max:70'
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
            'lista_id.required' => 'El ID de la lista es obligatorio.',
            'lista_id.integer' => 'El ID de la lista debe ser un número entero.',
            'lista_id.exists' => 'La lista seleccionada no existe.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede superar los 20 caracteres.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 70 caracteres.',
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
            'nombre' => 'nombre'
        ];
    }
}
