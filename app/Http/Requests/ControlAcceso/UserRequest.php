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
        $userId = $this->route('id'); // Obtener el ID del usuario en caso de actualización

        return [
            'num_docu' => 'required|string|max:20|unique:users,num_docu,' . $userId,
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
            'email' => 'required|string|email|max:70|unique:users,email,' . $userId,
            'password' => $this->isMethod('post') ? 'required|string|min:6' : 'nullable|string|min:6',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'firma' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|exists:roles,name',
            'cargo_id' => 'required|integer|exists:calidad_organigrama,id',
            'divi_poli_id' => 'required|integer|exists:config_divi_poli,id', // Ahora es obligatorio
        ];
    }

    public function messages()
    {
        return [
            'num_docu.unique' => 'El número de documento ya está en uso',
            'num_docu.required' => 'Te hizo falta el número de documento',
            'nombres.required' => 'Te hizo falta el nombre',
            'apellidos.required' => 'Te hizo falta el apellido',
            'email.required' => 'Te hizo falta el correo electrónico',
            'email.email' => 'El correo electrónico no es válido',
            'email.max' => 'El correo electrónico es demasiado largo',
            'email.unique' => 'El correo electrónico ya está en uso',
            'password.required' => 'Te hizo falta la contraseña',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'roles.required' => 'Debe asignar al menos un rol.',
            'roles.array' => 'El campo roles debe ser un arreglo.',
            'roles.*.exists' => 'El rol ":input" no existe en el sistema.',
            'cargo_id.required' => 'Debe seleccionar un cargo.',
            'cargo_id.integer' => 'El cargo seleccionado no es válido.',
            'cargo_id.exists' => 'El cargo seleccionado no existe en el sistema.',
            'avatar.file' => 'El avatar debe ser un archivo.',
            'avatar.mimes' => 'El avatar debe ser una imagen en formato JPEG, PNG, JPG, GIF o SVG.',
            'avatar.max' => 'El avatar no debe superar los 2MB.',
            'firma.file' => 'La firma debe ser un archivo.',
            'firma.mimes' => 'La firma debe ser una imagen en formato JPEG, PNG, JPG, GIF o SVG.',
            'firma.max' => 'La firma no debe superar los 2MB.'
        ];
    }
}
