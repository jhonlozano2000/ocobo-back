<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

class StoreWorkFlowTareaRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Tareas -> Crear');
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:5000',
            'instrucciones' => 'nullable|string|max:10000',
            'responsable_usuario_id' => 'nullable|exists:users,id',
            'tiempo_limite_horas' => 'nullable|integer|min:1|max:8760',
            'orden' => 'nullable|integer|min:0',
            'adjuntos_permitidos' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título de la tarea es obligatorio',
            'responsable_usuario_id.exists' => 'El usuario responsable no existe',
            'tiempo_limite_horas.min' => 'El tiempo límite debe ser al menos 1 hora',
            'tiempo_limite_horas.max' => 'El tiempo límite no puede exceder 8760 horas (1 año)',
        ];
    }
}
