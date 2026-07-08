<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

class StoreNodoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        return [
            'nodos' => 'required|array|min:1',
            'nodos.*.tipo' => 'required|in:inicio,tarea,condicion,notificacion,fin',
            'nodos.*.titulo' => 'required|string|max:255',
            'nodos.*.descripcion' => 'nullable|string',
            'nodos.*.posicion_x' => 'required|numeric',
            'nodos.*.posicion_y' => 'required|numeric',
            'nodos.*.configuracion_json' => 'nullable|json',
            'nodos.*.orden_ejecucion' => 'nullable|integer|min:0',
            'nodos.*.responsable_usuario_id' => 'nullable|exists:users,id',
            'nodos.*.tiempo_limite_horas' => 'nullable|integer|min:1|max:8760',
            'nodos.*.adjuntos_permitidos' => 'nullable|boolean',

            'conexiones' => 'required|array',
            'conexiones.*.nodo_origen_id' => 'required|integer',
            'conexiones.*.nodo_destino_id' => 'required|integer',
            'conexiones.*.etiqueta' => 'nullable|string|max:255',
            'conexiones.*.condicion_json' => 'nullable|json',
        ];
    }

    public function messages(): array
    {
        return [
            'nodos.required' => 'Debe enviar al menos un nodo',
            'nodos.*.tipo.required' => 'El tipo de nodo es obligatorio',
            'nodos.*.tipo.in' => 'El tipo de nodo no es válido',
            'nodos.*.titulo.required' => 'El título del nodo es obligatorio',
            'nodos.*.posicion_x.required' => 'La posición X es obligatoria',
            'nodos.*.posicion_y.required' => 'La posición Y es obligatoria',
        ];
    }
}
