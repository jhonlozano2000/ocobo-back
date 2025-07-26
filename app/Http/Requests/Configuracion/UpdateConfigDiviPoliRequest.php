<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfigDiviPoliRequest extends FormRequest
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
        $diviPoliId = $this->route('config_divi_poli');

        return [
            'parent' => [
                'nullable',
                'integer',
                'exists:config_divi_poli,id',
                Rule::notIn([$diviPoliId]) // No puede ser su propio padre
            ],
            'codigo' => [
                'sometimes',
                'string',
                'max:5',
                Rule::unique('config_divi_poli', 'codigo')->ignore($diviPoliId)
            ],
            'nombre' => [
                'sometimes',
                'string',
                'max:70'
            ],
            'tipo' => [
                'sometimes',
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
            'parent.not_in' => 'Una división política no puede ser su propio padre.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede tener más de 5 caracteres.',
            'codigo.unique' => 'El código ya está en uso, por favor elija otro.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 70 caracteres.',
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
