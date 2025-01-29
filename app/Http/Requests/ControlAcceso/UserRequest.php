<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
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
        $userId = $this->route('id'); // Captura el ID del usuario en caso de actualización

        return [
            'divi_poli_id' => 'required|integer|exists:config_divi_poli,id',
            'num_docu' => 'required|string|max:20|unique:users,num_docu,' . $userId,
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
            'tel' => 'nullable|string|max:15',
            'movil' => 'nullable|string|max:15',
            'dir' => 'nullable|string|max:100',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'firma' => 'nullable|string|max:100',
            'avatar' => 'nullable|string|max:100',
            'password' => $this->isMethod('post') ? 'required|string|min:6' : 'nullable|string|min:6',
            'estado' => 'boolean',
        ];
    }


    public function messages()
    {
        return [
            'divi_poli_id.integer' => 'La división política debe ser un número entero.',
            'divi_poli_id.exists' => 'La división política seleccionada no es válida.',
            'divi_poli_id.required' => 'Te hizo falra la división política.',

            'num_docu.required' => 'El número de documento es obligatorio.',
            'num_docu.string' => 'El número de documento debe ser una cadena de texto.',
            'num_docu.max' => 'El número de documento no debe superar los 20 caracteres.',
            'num_docu.unique' => 'El número de documento ya está registrado.',

            'nombres.required' => 'El nombre es obligatorio.',
            'nombres.string' => 'El nombre debe ser una cadena de texto.',
            'nombres.max' => 'El nombre no debe superar los 70 caracteres.',

            'apellidos.required' => 'El apellido es obligatorio.',
            'apellidos.string' => 'El apellido debe ser una cadena de texto.',
            'apellidos.max' => 'El apellido no debe superar los 70 caracteres.',

            'tel.string' => 'El teléfono debe ser una cadena de texto.',
            'tel.max' => 'El teléfono no debe superar los 15 caracteres.',

            'movil.string' => 'El móvil debe ser una cadena de texto.',
            'movil.max' => 'El móvil no debe superar los 15 caracteres.',

            'dir.string' => 'La dirección debe ser una cadena de texto.',
            'dir.max' => 'La dirección no debe superar los 100 caracteres.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.string' => 'El correo electrónico debe ser una cadena de texto.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.max' => 'El correo electrónico no debe superar los 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está registrado.',

            'firma.string' => 'La firma debe ser una cadena de texto.',
            'firma.max' => 'La firma no debe superar los 100 caracteres.',

            'avatar.string' => 'El avatar debe ser una cadena de texto.',
            'avatar.max' => 'El avatar no debe superar los 100 caracteres.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',

            'estado.boolean' => 'El estado debe ser un valor booleano.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'status' => false,
            'message' => 'Errores de validación.',
            'errors' => $validator->errors(),
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}
