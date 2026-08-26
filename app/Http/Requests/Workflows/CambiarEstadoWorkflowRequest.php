<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;

class CambiarEstadoWorkflowRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        return [
            'estado' => 'required|in:borrador,activo,inactivo,archivado',
        ];
    }
}
