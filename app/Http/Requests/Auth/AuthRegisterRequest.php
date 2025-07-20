<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AuthRegisterRequest extends FormRequest
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
     * @return array<string, string>
     */
    public function rules()
    {
        return [
            'num_docu' => 'required|string|max:20|unique:users',
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
            'email' => 'required|string|email|max:70|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'tel' => 'nullable|string|max:20',
            'movil' => 'nullable|string|max:20',
            'dir' => 'nullable|string|max:255',
            'role' => 'nullable|string|exists:roles,name',
        ];
    }

    /**
     * Custom messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'num_docu.required' => 'El número de documento es obligatorio.',
            'num_docu.unique' => 'El número de documento ya está en uso.',
            'num_docu.max' => 'El número de documento no puede superar los 20 caracteres.',

            'nombres.required' => 'El nombre es obligatorio.',
            'nombres.max' => 'El nombre no puede superar los 70 caracteres.',

            'apellidos.required' => 'El apellido es obligatorio.',
            'apellidos.max' => 'El apellido no puede superar los 70 caracteres.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'email.unique' => 'El correo electrónico ya está en uso.',
            'email.max' => 'El correo electrónico no puede superar los 70 caracteres.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',

            'tel.max' => 'El teléfono no puede superar los 20 caracteres.',
            'movil.max' => 'El móvil no puede superar los 20 caracteres.',
            'dir.max' => 'La dirección no puede superar los 255 caracteres.',

            'role.exists' => 'El rol especificado no existe.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'status'  => false,
            'message' => 'Errores de validación.',
            'errors'  => $validator->errors(),
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}
