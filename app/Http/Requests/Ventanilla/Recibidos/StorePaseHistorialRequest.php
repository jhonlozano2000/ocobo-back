<?php

namespace App\Http\Requests\Ventanilla\Recibidos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaseHistorialRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'usuario_origen_id' => 'nullable|integer|exists:users,id',
            'usuario_destino_id' => 'nullable|integer|exists:users,id',
            'users_cargos_destino_id' => 'nullable|integer|exists:users_cargos,id',
            'tipo' => 'nullable|in:pase,asignacion_inicial,reasignacion',
        ];
    }

    public function messages()
    {
        return [
            'usuario_destino_id.exists' => 'El usuario destino no existe.',
            'users_cargos_destino_id.exists' => 'El cargo del usuario destino no existe.',
            'usuario_origen_id.exists' => 'El usuario origen no existe.',
            'tipo.in' => 'El tipo debe ser: pase, asignacion_inicial o reasignacion.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (! $this->filled('usuario_destino_id') && ! $this->filled('users_cargos_destino_id')) {
                $validator->errors()->add(
                    'usuario_destino_id',
                    'Debe proporcionar el usuario destino o el cargo del usuario destino.'
                );
            }
        });
    }
}