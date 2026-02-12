<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVentanillaRadicaEnviadosResponsaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'radica_enviado_id' => 'nullable|integer|exists:ventanilla_radica_enviados,id',
            'users_cargos_id' => 'nullable|integer|exists:users_cargos,id',
            'custodio' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'radica_enviado_id.exists' => 'El radicado enviado seleccionado no existe.',
            'users_cargos_id.exists' => 'El cargo del usuario no existe.',
            'custodio.boolean' => 'El campo custodio debe ser verdadero o falso.',
        ];
    }
}
