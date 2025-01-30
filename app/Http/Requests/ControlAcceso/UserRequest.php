<?php

namespace App\Http\Requests\ControlAcceso;

use App\Models\Calidad\CalidadOrganigrama;
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
            'email' => 'required|string|email|max:70|unique:users,email,' . $userId,
            'password' => $this->isMethod('post') ? 'required|string|min:6|confirmed' : 'nullable|string|min:6|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|exists:roles,name',
            'cargo_id' => [
                'required',
                'integer',
                'exists:calidad_organigrama,id',
                function ($attribute, $value, $fail) {
                    // Realiza una única consulta para verificar si el ID es válido y de tipo "Cargo"
                    if (!CalidadOrganigrama::where('id', $value)->where('tipo', 'Cargo')->exists()) {
                        $this->isMethod('post') ?  $fail('El usuario no se pudo crear ya que no se está relacionando a algún cargo.') : $fail('El usuario no se pudo actualiza ya que no se está relacionando a algún cargo.');
                    }
                },
            ],
        ];
    }

    public function messages()
    {
        return [
            'num_docu.required' => 'El número de documento es obligatorio.',
            'num_docu.unique' => 'El número de documento ya está en uso.',
            'nombres.required' => 'El nombre es obligatorio.',
            'nombres.max' => 'El nombre no puede superar los 70 caracteres.',
            'apellidos.required' => 'El apellido es obligatorio.',
            'apellidos.max' => 'El apellido no puede superar los 70 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'email.unique' => 'El correo electrónico ya está en uso.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'roles.required' => 'Debe asignar al menos un rol.',
            'roles.array' => 'El campo roles debe ser un arreglo.',
            'roles.*.exists' => 'El rol ":input" no existe en el sistema.',
            'cargo_id.required' => 'Debe seleccionar un cargo válido.',
            'cargo_id.exists' => 'El cargo asignado no existe en el sistema.',
            'divi_poli_id.required' => 'Debe seleccionar una división política.',
            'divi_poli_id.exists' => 'La división política seleccionada no existe.',
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
