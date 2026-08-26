<?php
declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'workflow_id' => 'nullable|integer|exists:workflows,id',
            'nombre' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_limite' => 'nullable|date',
            'estado' => 'nullable|in:pendiente,en_curso,completada,vencida,cancelada',
            'propietarios' => 'nullable|array',
            'propietarios.*' => 'integer|exists:users,id',
            'responsables' => 'nullable|array',
            'responsables.*' => 'integer|exists:users,id',
            'checklists' => 'nullable|array',
            'checklists.*.id' => 'nullable|integer|exists:tarea_checklists,id',
            'checklists.*.item_descripcion' => 'required_with:checklists|string|max:500',
            'checklists.*.esta_completado' => 'nullable|boolean',
        ];
    }
}
