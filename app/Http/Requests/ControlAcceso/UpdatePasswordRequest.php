<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'current_password.string' => 'La contraseña actual debe ser una cadena de texto.',
            'password.required' => 'Debes ingresar una nueva contraseña.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.password.mixed' => 'La nueva contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.password.numbers' => 'La nueva contraseña debe contener al menos un número.',
            'password.password.symbols' => 'La nueva contraseña debe contener al menos un símbolo.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'contraseña actual',
            'password' => 'nueva contraseña',
        ];
    }
}
