<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreNodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        return [
            'nodos' => 'required|array',
            'nodos.*.id' => 'required|string',
            'nodos.*.tipo' => 'required|string|in:inicio,fin,tarea,notificacion,condicion',
            'nodos.*.titulo' => 'required|string|max:255',
            'nodos.*.descripcion' => 'nullable|string|max:1000',
            'nodos.*.posicion_x' => 'required|numeric',
            'nodos.*.posicion_y' => 'required|numeric',
            'nodos.*.configuracion_json' => 'nullable|array',
            'nodos.*.responsable_usuario_id' => 'nullable|integer|exists:users,id',
            'nodos.*.tiempo_limite_horas' => 'nullable|integer|min:1',
            'nodos.*.adjuntos_permitidos' => 'nullable|boolean',
            'nodos.*.orden_ejecucion' => 'nullable|integer|min:1',
            'conexiones' => 'required|array',
            'conexiones.*.id' => 'required|string',
            'conexiones.*.nodo_origen_id' => 'required|string',
            'conexiones.*.nodo_destino_id' => 'required|string',
            'conexiones.*.etiqueta' => 'nullable|string|max:255',
            'conexiones.*.condicion_json' => 'nullable|array',
        ];
    }
}
