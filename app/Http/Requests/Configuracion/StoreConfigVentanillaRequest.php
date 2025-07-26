<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigVentanillaRequest extends FormRequest
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
            'sede_id' => [
                'required',
                'integer',
                'exists:config_sedes,id'
            ],
            'nombre' => [
                'required',
                'string',
                'max:100'
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255'
            ],
            'codigo' => [
                'required',
                'string',
                'max:20',
                'unique:config_ventanillas,codigo'
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
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'El ID de la sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'nombre.required' => 'El nombre de la ventanilla es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede superar los 20 caracteres.',
            'codigo.unique' => 'El código ya está en uso, por favor elija otro.',
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
            'sede_id' => 'sede',
            'nombre' => 'nombre de la ventanilla',
            'descripcion' => 'descripción',
            'codigo' => 'código',
            'estado' => 'estado'
        ];
    }
}
