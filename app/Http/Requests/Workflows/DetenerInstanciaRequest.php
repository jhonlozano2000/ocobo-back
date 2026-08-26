<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;

class DetenerInstanciaRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    public function rules(): array
    {
        return [
            'estado' => 'sometimes|in:detenida,cancelada',
        ];
    }
}
