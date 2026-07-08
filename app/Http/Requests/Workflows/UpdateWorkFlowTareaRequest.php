<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

class UpdateWorkFlowTareaRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string|max:5000',
            'instrucciones' => 'nullable|string|max:10000',
            'responsable_usuario_id' => 'nullable|exists:users,id',
            'tiempo_limite_horas' => 'nullable|integer|min:1|max:8760',
            'estado' => 'sometimes|in:pendiente,en_curso,completada,vencida,cancelada',
            'orden' => 'nullable|integer|min:0',
            'adjuntos_permitidos' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título de la tarea es obligatorio',
            'estado.in' => 'El estado debe ser: pendiente, en_curso, completada, vencida o cancelada',
            'responsable_usuario_id.exists' => 'El usuario responsable no existe',
            'tiempo_limite_horas.min' => 'El tiempo límite debe ser al menos 1 hora',
        ];
    }
}
