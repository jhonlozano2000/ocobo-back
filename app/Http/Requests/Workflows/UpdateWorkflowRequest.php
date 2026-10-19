<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use App\Models\Workflows\WorkflowSetting;

class UpdateWorkflowRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string|max:5000',
            'estado' => 'sometimes|in:borrador,activo,inactivo,archivado',
            'tiempo_finalizacion_horas' => 'nullable|integer|min:1|max:87600',
            'tiempo_finalizacion_unidad' => 'nullable|in:horas,dias_habiles',
            'administrador_user_id' => 'nullable|exists:users,id,estado,1',
            'estrategia_asignacion' => 'sometimes|in:'.implode(',', WorkflowSetting::ESTRATEGIAS),
            'equipo_ids' => 'sometimes|array|max:50',
            'equipo_ids.*' => 'integer|distinct|exists:users,id,estado,1',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del flujo es obligatorio',
            'estado.in' => 'El estado debe ser: borrador, activo, inactivo o archivado',
            'tiempo_finalizacion_horas.min' => 'El tiempo debe ser al menos 1 hora',
            'tiempo_finalizacion_horas.max' => 'El tiempo no puede exceder 87600 horas (10 años)',
            'administrador_user_id.exists' => 'El administrador seleccionado no existe o está inactivo',
            'estrategia_asignacion.in' => 'La estrategia de asignación debe ser: manual, fila o autoasignar',
            'equipo_ids.*.exists' => 'Un miembro del equipo no existe o está inactivo',
        ];
    }
}
