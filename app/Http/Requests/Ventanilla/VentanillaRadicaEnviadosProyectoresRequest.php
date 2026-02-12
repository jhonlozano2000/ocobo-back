<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class VentanillaRadicaEnviadosProyectoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proyectores' => 'required|array|min:1',
            'proyectores.*.radica_enviado_id' => 'nullable|integer|exists:ventanilla_radica_enviados,id',
            'proyectores.*.users_cargos_id' => 'required|integer|exists:users_cargos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'proyectores.required' => 'El array de proyectores es obligatorio.',
            'proyectores.array' => 'Los proyectores deben ser un array.',
            'proyectores.min' => 'Debe enviar al menos un proyector.',
            'proyectores.*.users_cargos_id.required' => 'El ID del cargo del usuario es obligatorio.',
            'proyectores.*.users_cargos_id.exists' => 'El cargo del usuario no existe.',
        ];
    }
}
