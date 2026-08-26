<?php
declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Tareas -> Editar');
    }

    public function rules(): array
    {
        return [
            'item_descripcion' => 'nullable|string|max:500',
            'esta_completado' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0',
        ];
    }
}
