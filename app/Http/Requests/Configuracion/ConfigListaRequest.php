<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigListaRequest extends FormRequest
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
        return [
            'cod' => [
                'required',
                'string',
                'max:10',
                Rule::unique('config_listas', 'cod')->ignore($this->lista), // Ignorar el ID actual
            ],
            'nombre' => 'required|string|max:70',
        ];
    }

    public function messages()
    {
        return [
            'cod.required' => 'El código es obligatorio.',
            'cod.string' => 'El código debe ser una cadena de texto.',
            'cod.max' => 'El código no puede tener más de 10 caracteres.',
            'cod.unique' => 'El código ya está en uso, por favor elija otro.',

            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 70 caracteres.',
        ];
    }
}
