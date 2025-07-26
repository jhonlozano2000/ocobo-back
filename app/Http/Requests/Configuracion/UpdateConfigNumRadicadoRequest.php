<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigNumRadicadoRequest extends FormRequest
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
            'formato' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_#]+$/'
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
            'formato.required' => 'El formato es obligatorio.',
            'formato.string' => 'El formato debe ser una cadena de texto.',
            'formato.max' => 'El formato no puede superar los 50 caracteres.',
            'formato.regex' => 'El formato solo puede contener letras mayúsculas, números, guiones, guiones bajos y símbolos #.',
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
            'formato' => 'formato de numeración'
        ];
    }
}
