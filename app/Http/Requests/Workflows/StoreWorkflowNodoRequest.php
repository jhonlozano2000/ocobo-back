<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;

class StoreWorkflowNodoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        return [
            'tipo' => 'required|in:inicio,tarea,condicion,notificacion,fin',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'posicion_x' => 'required|numeric',
            'posicion_y' => 'required|numeric',
            // El modelo castea a array: validar como array, no como string JSON
            'configuracion_json' => 'nullable|array',
            'responsable_usuario_id' => 'nullable|integer|exists:users,id',
            'tiempo_limite_horas' => 'nullable|integer|min:1',
        ];
    }
}
