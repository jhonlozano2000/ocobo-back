<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workflow_id' => 'nullable|integer|exists:workflows,id',
            'nombre' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_limite' => 'nullable|date',
            'estado' => 'nullable|in:pendiente,en_progreso,completada',
            'propietarios' => 'nullable|array',
            'propietarios.*' => 'integer|exists:users,id',
            'responsables' => 'nullable|array',
            'responsables.*' => 'integer|exists:users,id',
            'checklists' => 'nullable|array',
            'checklists.*.id' => 'nullable|integer',
            'checklists.*.item_descripcion' => 'required_with:checklists|string|max:500',
            'checklists.*.esta_completado' => 'nullable|boolean',
        ];
    }
}
