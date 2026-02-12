<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class VentanillaRadicaEnviadosFirmasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firmas' => 'required|array|min:1',
            'firmas.*.radica_enviado_id' => 'nullable|integer|exists:ventanilla_radica_enviados,id',
            'firmas.*.users_cargos_id' => 'required|integer|exists:users_cargos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'firmas.required' => 'El array de firmantes es obligatorio.',
            'firmas.array' => 'Los firmantes deben ser un array.',
            'firmas.min' => 'Debe enviar al menos un firmante.',
            'firmas.*.users_cargos_id.required' => 'El ID del cargo del usuario es obligatorio.',
            'firmas.*.users_cargos_id.exists' => 'El cargo del usuario no existe.',
        ];
    }
}
