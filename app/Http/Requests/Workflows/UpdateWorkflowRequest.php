<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

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
            'administrador_user_id' => 'nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del flujo es obligatorio',
            'estado.in' => 'El estado debe ser: borrador, activo, inactivo o archivado',
            'tiempo_finalizacion_horas.min' => 'El tiempo debe ser al menos 1 hora',
            'tiempo_finalizacion_horas.max' => 'El tiempo no puede exceder 87600 horas (10 años)',
            'administrador_user_id.exists' => 'El administrador seleccionado no existe',
        ];
    }
}
