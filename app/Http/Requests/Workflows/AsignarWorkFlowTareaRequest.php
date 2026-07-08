<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

class AsignarWorkFlowTareaRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Tareas -> Asignar');
    }

    public function rules(): array
    {
        return [
            'responsable_usuario_id' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'responsable_usuario_id.required' => 'Debe seleccionar un usuario responsable',
            'responsable_usuario_id.exists' => 'El usuario seleccionado no existe',
        ];
    }
}
