<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigDiviPoliRequest extends FormRequest
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
            'parent' => [
                'nullable',
                'integer',
                'exists:config_divi_poli,id'
            ],
            'codigo' => [
                'required',
                'string',
                'max:5',
                'unique:config_divi_poli,codigo'
            ],
            'nombre' => [
                'required',
                'string',
                'max:70'
            ],
            'tipo' => [
                'required',
                'string',
                'max:15',
                'in:Pais,Departamento,Municipio'
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
            'parent.integer' => 'El ID de la división política padre debe ser un número entero.',
            'parent.exists' => 'La división política padre seleccionada no existe.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede tener más de 5 caracteres.',
            'codigo.unique' => 'El código ya está en uso, por favor elija otro.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 70 caracteres.',
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.string' => 'El tipo debe ser una cadena de texto.',
            'tipo.max' => 'El tipo no puede tener más de 15 caracteres.',
            'tipo.in' => 'El tipo debe ser Pais, Departamento o Municipio.',
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
            'parent' => 'división política padre',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'tipo' => 'tipo'
        ];
    }
}
