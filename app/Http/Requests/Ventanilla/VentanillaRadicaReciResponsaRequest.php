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
            'responsables.*.users_cargos_id' => 'required|exists:users_cargos,id',
            'responsables.*.custodio' => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'responsables.required' => 'El array de responsables es obligatorio.',
            'responsables.array' => 'Los responsables deben ser un array.',
            'responsables.min' => 'Debe enviar al menos un responsable.',
            'responsables.*.users_cargos_id.required' => 'El ID del cargo del usuario es obligatorio.',
            'responsables.*.users_cargos_id.exists' => 'El cargo del usuario proporcionado no existe.',
            'responsables.*.custodio.required' => 'El campo custodio es obligatorio.',
            'responsables.*.custodio.boolean' => 'El campo custodio debe ser verdadero o falso.',
        ];
    }
}
