<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workflow_id' => 'required|integer|exists:workflows,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_limite' => 'nullable|date',
            'estado' => 'nullable|in:pendiente,en_progreso,completada',
            'propietarios' => 'nullable|array',
            'propietarios.*' => 'integer|exists:users,id',
            'responsables' => 'nullable|array',
            'responsables.*' => 'integer|exists:users,id',
            'checklists' => 'nullable|array',
            'checklists.*.item_descripcion' => 'required_with:checklists|string|max:500',
            'checklists.*.esta_completado' => 'nullable|boolean',
        ];
    }
}
