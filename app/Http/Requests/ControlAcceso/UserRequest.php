<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, string>
     */
    public function rules()
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;
        $isCreate = $this->isMethod('post');

        return [
            'divi_poli_id' => [
                $isCreate ? 'required' : 'sometimes',
                'integer',
                'exists:config_divi_poli,id'
            ],

            'num_docu' => [
                $isCreate ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'num_docu')->ignore($userId)
            ],

            'nombres' => [
                $isCreate ? 'required' : 'sometimes',
                'string',
                'max:70'
            ],

            'apellidos' => [
                $isCreate ? 'required' : 'sometimes',
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
                $isCreate ? 'required' : 'sometimes',
                'string',
                'email',
                'max:70',
                Rule::unique('users', 'email')->ignore($userId)
            ],

            'password' => [
                $isCreate ? 'required' : 'nullable',
                'string',
                'min:6'
            ],

            'estado' => [
                'nullable',
                'boolean'
            ],

            'roles' => [
                $isCreate ? 'required' : 'sometimes',
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
     * Custom messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'divi_poli_id.required' => 'Debe seleccionar una división política.',
            'divi_poli_id.exists'   => 'La división política seleccionada no existe.',

            'num_docu.required'     => 'El número de documento es obligatorio.',
            'num_docu.unique'       => 'El número de documento ya está en uso.',
            'num_docu.max'          => 'El número de documento no puede superar los 20 caracteres.',

            'nombres.required'      => 'El nombre es obligatorio.',
            'nombres.max'           => 'El nombre no puede superar los 70 caracteres.',

            'apellidos.required'    => 'El apellido es obligatorio.',
            'apellidos.max'         => 'El apellido no puede superar los 70 caracteres.',

            'tel.max'               => 'El teléfono no puede superar los 20 caracteres.',

            'movil.max'             => 'El móvil no puede superar los 20 caracteres.',

            'dir.max'               => 'La dirección no puede superar los 255 caracteres.',

            'email.required'        => 'El correo electrónico es obligatorio.',
            'email.email'           => 'El formato del correo electrónico no es válido.',
            'email.unique'          => 'El correo electrónico ya está en uso.',
            'email.max'             => 'El correo electrónico no puede superar los 70 caracteres.',

            'password.required'     => 'La contraseña es obligatoria.',
            'password.min'          => 'La contraseña debe tener al menos 6 caracteres.',

            'estado.boolean'        => 'El estado debe ser verdadero o falso.',

            'roles.required'        => 'Debe asignar al menos un rol.',
            'roles.array'           => 'El campo roles debe ser un arreglo.',
            'roles.min'             => 'Debe asignar al menos un rol.',
            'roles.*.exists'        => 'El rol ":input" no existe en el sistema.',

            'avatar.file'           => 'El avatar debe ser un archivo.',
            'avatar.image'          => 'El avatar debe ser una imagen.',
            'avatar.max'            => 'El avatar no puede superar los 2MB.',

            'firma.file'            => 'La firma debe ser un archivo.',
            'firma.image'           => 'La firma debe ser una imagen.',
            'firma.max'             => 'La firma no puede superar los 2MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes()
    {
        return [
            'divi_poli_id' => 'división política',
            'num_docu'     => 'número de documento',
            'nombres'      => 'nombres',
            'apellidos'    => 'apellidos',
            'tel'          => 'teléfono',
            'movil'        => 'móvil',
            'dir'          => 'dirección',
            'email'        => 'correo electrónico',
            'password'     => 'contraseña',
            'estado'       => 'estado',
            'roles'        => 'roles',
            'avatar'       => 'avatar',
            'firma'        => 'firma',
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
