<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReordenarChecklistRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'orden' => 'required|array',
            'orden.*' => [
                'required',
                'integer',
                Rule::exists('tarea_checklists', 'id')->where('tarea_id', $this->route('tarea')),
            ],
        ];
    }
}
