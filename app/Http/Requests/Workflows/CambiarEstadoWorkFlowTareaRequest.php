<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

class CambiarEstadoWorkFlowTareaRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'estado' => 'required|in:pendiente,en_curso,completada,vencida,cancelada',
            'resultado' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio',
            'estado.in' => 'El estado debe ser: pendiente, en_curso, completada, vencida o cancelada',
        ];
    }
}
