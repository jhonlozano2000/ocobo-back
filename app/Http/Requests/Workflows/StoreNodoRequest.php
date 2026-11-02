<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('Workflows -> Workflows -> Editar');
    }

    public function rules(): array
    {
        $clientIds = collect($this->input('nodos', []))->pluck('client_id')->filter(fn ($id) => is_string($id))->values()->all();

        return [
            // El builder identifica cada nodo con un client_id temporal; el servicio lo traduce al id real
            'nodos' => 'present|array',
            'nodos.*.client_id' => 'required|string|max:100|distinct',
            'nodos.*.tipo' => 'required|string|in:inicio,fin,tarea,notificacion,condicion,paralelo,union',
            'nodos.*.titulo' => 'required|string|max:255',
            'nodos.*.descripcion' => 'nullable|string|max:1000',
            'nodos.*.posicion_x' => 'required|numeric',
            'nodos.*.posicion_y' => 'required|numeric',
            'nodos.*.configuracion_json' => 'nullable|array',
            'nodos.*.responsable_usuario_id' => 'nullable|integer|exists:users,id,estado,1',
            'nodos.*.tiempo_limite_horas' => 'nullable|integer|min:1',
            'nodos.*.tiempo_limite_unidad' => 'nullable|in:horas,dias_habiles',
            'nodos.*.adjuntos_permitidos' => 'nullable|boolean',
            'nodos.*.orden_ejecucion' => 'nullable|integer|min:0',
            'conexiones' => 'present|array',
            'conexiones.*.nodo_origen_id' => ['required', 'string', Rule::in($clientIds)],
            'conexiones.*.nodo_destino_id' => ['required', 'string', Rule::in($clientIds)],
            'conexiones.*.etiqueta' => 'nullable|string|max:255',
            'conexiones.*.condicion_json' => 'nullable|array',
            // Notas, textos, formas y resaltados del lienzo: solo visuales, fuera del grafo
            'anotaciones' => 'sometimes|array|max:300',
            'anotaciones.*.id' => 'required|string|max:100|distinct',
            'anotaciones.*.tipo' => 'required|string|in:nota,texto,forma,trazo',
            'anotaciones.*.x' => 'required|numeric',
            'anotaciones.*.y' => 'required|numeric',
            'anotaciones.*.ancho' => 'nullable|numeric|min:1|max:5000',
            'anotaciones.*.alto' => 'nullable|numeric|min:1|max:5000',
            'anotaciones.*.texto' => 'nullable|string|max:2000',
            'anotaciones.*.estilo' => 'nullable|array:color,figura,tamano,negrita,cursiva',
            'anotaciones.*.estilo.color' => 'nullable|string|max:30',
            'anotaciones.*.estilo.figura' => 'nullable|in:rectangulo,elipse,flecha',
            'anotaciones.*.estilo.tamano' => 'nullable|integer|min:8|max:96',
            'anotaciones.*.estilo.negrita' => 'nullable|boolean',
            'anotaciones.*.estilo.cursiva' => 'nullable|boolean',
            'anotaciones.*.puntos' => 'nullable|array|max:2000',
            'anotaciones.*.puntos.*' => 'array|size:2',
            'anotaciones.*.puntos.*.*' => 'numeric',
        ];
    }
}
