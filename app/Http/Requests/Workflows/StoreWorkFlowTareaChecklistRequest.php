<?php
declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreWorkFlowTareaChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'item_descripcion' => 'required|string|max:500',
            'esta_completado' => 'nullable|boolean',
        ];
    }
}
