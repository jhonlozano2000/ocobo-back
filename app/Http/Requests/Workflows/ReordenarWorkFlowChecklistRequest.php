<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReordenarWorkFlowChecklistRequest extends SanitizedFormRequest
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
                Rule::exists('work_flow_tarea_checklists', 'id')->where('work_flow_tarea_id', $this->route('tarea')),
            ],
        ];
    }
}
