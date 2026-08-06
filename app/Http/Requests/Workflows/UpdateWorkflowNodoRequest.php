<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowNodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        return [
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'posicion_x' => 'sometimes|numeric',
            'posicion_y' => 'sometimes|numeric',
            'configuracion_json' => 'nullable|json',
        ];
    }
}
