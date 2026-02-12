<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class VentanillaRadicaEnviadosResponsaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'responsables' => 'required|array|min:1',
            'responsables.*.radica_enviado_id' => 'nullable|integer|exists:ventanilla_radica_enviados,id',
            'responsables.*.users_cargos_id' => 'required|integer|exists:users_cargos,id',
            'responsables.*.custodio' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'responsables.required' => 'El array de responsables es obligatorio.',
            'responsables.array' => 'Los responsables deben ser un array.',
            'responsables.min' => 'Debe enviar al menos un responsable.',
            'responsables.*.users_cargos_id.required' => 'El ID del cargo del usuario es obligatorio.',
            'responsables.*.users_cargos_id.integer' => 'El ID del cargo del usuario debe ser un número entero.',
            'responsables.*.users_cargos_id.exists' => 'El cargo del usuario proporcionado no existe.',
            'responsables.*.custodio.required' => 'El campo custodio es obligatorio.',
            'responsables.*.custodio.boolean' => 'El campo custodio debe ser verdadero o falso.',
        ];
    }
}
