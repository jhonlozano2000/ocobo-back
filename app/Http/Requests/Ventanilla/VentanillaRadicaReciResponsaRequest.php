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
            'responsables' => 'required|array|min:1',
            'responsables.*.radica_reci_id' => 'required|exists:ventanilla_radica_reci,id',
            'responsables.*.user_id' => 'required|exists:users,id',
            'responsables.*.custodio' => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'responsables.required' => 'El array de responsables es obligatorio.',
            'responsables.array' => 'Los responsables deben ser un array.',
            'responsables.min' => 'Debe enviar al menos un responsable.',
            'responsables.*.radica_reci_id.required' => 'El ID de la radicación es obligatorio.',
            'responsables.*.radica_reci_id.exists' => 'La radicación proporcionada no existe.',
            'responsables.*.user_id.required' => 'El ID del usuario es obligatorio.',
            'responsables.*.user_id.exists' => 'El usuario proporcionado no existe.',
            'responsables.*.custodio.required' => 'El campo custodio es obligatorio.',
            'responsables.*.custodio.boolean' => 'El campo custodio debe ser verdadero o falso.',
        ];
    }
}
