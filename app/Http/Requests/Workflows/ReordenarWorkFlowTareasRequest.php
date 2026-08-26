<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReordenarWorkFlowTareasRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'tareas' => 'required|array',
            'tareas.*' => [
                'required',
                'integer',
                Rule::exists('work_flow_tareas', 'id')->where('nodo_id', $this->route('nodo')),
            ],
        ];
    }
}
