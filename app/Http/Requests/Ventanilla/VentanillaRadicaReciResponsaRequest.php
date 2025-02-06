<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class VentanillaRadicaReciResponsaRequest extends FormRequest
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
            '*.radica_reci_id' => 'required|exists:ventanilla_radica_reci,id',
            '*.users_cargos_id' => 'required|exists:users,id',
            '*.custodio' => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            '*.radica_reci_id.required' => 'El campo radica_reci_id es obligatorio.',
            '*.radica_reci_id.exists' => 'El radica_reci_id proporcionado no existe.',
            '*.users_cargos_id.required' => 'El campo users_cargos_id es obligatorio.',
            '*.users_cargos_id.exists' => 'El usuario proporcionado no existe.',
            '*.custodio.required' => 'El campo custodio es obligatorio.',
            '*.custodio.boolean' => 'El campo custodio debe ser verdadero o falso.',
        ];
    }
}
