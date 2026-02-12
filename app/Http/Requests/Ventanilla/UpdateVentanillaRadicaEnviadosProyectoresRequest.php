<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVentanillaRadicaEnviadosProyectoresRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'radica_enviado_id.exists' => 'El radicado enviado no existe.',
            'users_cargos_id.exists' => 'El cargo del usuario no existe.',
        ];
    }
}
