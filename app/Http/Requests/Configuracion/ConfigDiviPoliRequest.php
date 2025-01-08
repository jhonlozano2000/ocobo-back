<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigDiviPoliRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        $diviPoliId = $this->route('config_divi_poli');

        return [
            'parent' => 'nullable|exists:config_divi_poli,id',
            'codigo' => [
                'required',
                'string',
                'max:5',
                Rule::unique('config_divi_poli', 'codigo')->ignore($diviPoliId),
            ],
            'nombre' => 'required|string|max:70',
            'tipo' => 'required|string|max:15',
        ];
    }

    public function messages()
    {
        return [
            'parent.exists' => 'El ID de la división política padre no es válido.',
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
        ];
    }
}
