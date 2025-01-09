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
        return false;
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
            '*.users_cargos_id' => 'required|exists:users_cargos,id',
            '*.custodio' => 'nullable|boolean',
            '*.fechor_visto' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            '*.radica_reci_id.required' => 'El radicado es obligatorio.',
            '*.radica_reci_id.exists' => 'El radicado no existe.',
            '*.users_cargos_id.required' => 'El usuario con cargo es obligatorio.',
            '*.users_cargos_id.exists' => 'El usuario con cargo no es válido.',
            '*.custodio.boolean' => 'El campo custodio debe ser verdadero o falso.',
            '*.fechor_visto.date' => 'La fecha de visto debe ser una fecha válida.',
        ];
    }
}
