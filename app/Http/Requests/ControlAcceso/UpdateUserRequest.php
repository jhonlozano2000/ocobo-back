<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'divi_poli_id' => [
                'sometimes',
                'integer',
                'exists:config_divi_poli,id'
            ],

            'num_docu' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'num_docu')->ignore($user->id)
            ],

            'nombres' => [
                'sometimes',
                'string',
                'max:70'
            ],

            'apellidos' => [
                'sometimes',
                'string',
                'max:70'
            ],

            'tel' => [
                'nullable',
                'string',
                'max:20'
            ],

            'movil' => [
                'nullable',
                'string',
                'max:20'
            ],

            'dir' => [
                'nullable',
                'string',
                'max:255'
            ],

            'email' => [
                'sometimes',
                'string',
                'email',
                'max:70',
                Rule::unique('users', 'email')->ignore($user->id)
            ],

            'password' => [
                'nullable',
                'string',
                'min:6'
            ],

            'estado' => [
                'nullable',
                'in:0,1,true,false'
            ],

            'roles' => [
                'sometimes',
                'array',
                'min:1'
            ],

            'roles.*' => [
                'required',
                'string',
                'exists:roles,name'
            ],

            'avatar' => [
                'nullable',
                'file',
                'image',
                'max:2048' // 2MB máximo
            ],

            'firma' => [
                'nullable',
                'file',
                'image',
                'max:2048' // 2MB máximo
            ],
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
            'divi_poli_id.integer' => 'La división política debe ser un número entero.',
            'divi_poli_id.exists' => 'La división política seleccionada no existe.',

            'num_docu.string' => 'El número de documento debe ser una cadena de texto.',
            'num_docu.unique' => 'El número de documento ya está en uso.',
            'num_docu.max' => 'El número de documento no puede superar los 20 caracteres.',

            'nombres.string' => 'El nombre debe ser una cadena de texto.',
            'nombres.max' => 'El nombre no puede superar los 70 caracteres.',

            'apellidos.string' => 'El apellido debe ser una cadena de texto.',
            'apellidos.max' => 'El apellido no puede superar los 70 caracteres.',

            'tel.string' => 'El teléfono debe ser una cadena de texto.',
            'tel.max' => 'El teléfono no puede superar los 20 caracteres.',

            'movil.string' => 'El móvil debe ser una cadena de texto.',
            'movil.max' => 'El móvil no puede superar los 20 caracteres.',

            'dir.string' => 'La dirección debe ser una cadena de texto.',
            'dir.max' => 'La dirección no puede superar los 255 caracteres.',

            'email.string' => 'El correo electrónico debe ser una cadena de texto.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'email.unique' => 'El correo electrónico ya está en uso.',
            'email.max' => 'El correo electrónico no puede superar los 70 caracteres.',

            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',

            'estado.in' => 'El estado debe ser 0, 1, true o false.',

            'roles.array' => 'El campo roles debe ser un arreglo.',
            'roles.min' => 'Debe asignar al menos un rol.',
            'roles.*.required' => 'Cada rol debe ser especificado.',
            'roles.*.string' => 'Cada rol debe ser una cadena de texto.',
            'roles.*.exists' => 'El rol ":input" no existe en el sistema.',

            'avatar.file' => 'El avatar debe ser un archivo.',
            'avatar.image' => 'El avatar debe ser una imagen.',
            'avatar.max' => 'El avatar no puede superar los 2MB.',

            'firma.file' => 'La firma debe ser un archivo.',
            'firma.image' => 'La firma debe ser una imagen.',
            'firma.max' => 'La firma no puede superar los 2MB.',
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
            'divi_poli_id' => 'división política',
            'num_docu' => 'número de documento',
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'tel' => 'teléfono',
            'movil' => 'móvil',
            'dir' => 'dirección',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'estado' => 'estado',
            'roles' => 'roles',
            'roles.*' => 'rol',
            'avatar' => 'avatar',
            'firma' => 'firma',
        ];
    }
}
