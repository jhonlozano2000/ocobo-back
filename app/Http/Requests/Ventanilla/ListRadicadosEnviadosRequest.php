<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class ListRadicadosEnviadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'fecha_desde' => ['nullable', 'date', 'before_or_equal:fecha_hasta'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'clasifica_documen_id' => ['nullable', 'integer', 'exists:clasificacion_documental_trd,id'],
            'tercero_enviado_id' => ['nullable', 'integer', 'exists:gestion_terceros,id'],
            'medio_enviado_id' => ['nullable', 'integer', 'exists:config_listas_detalles,id'],
            'usuario_responsable' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'El término de búsqueda debe ser una cadena de texto.',
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'fecha_desde.date' => 'La fecha desde debe tener un formato válido.',
            'fecha_hasta.date' => 'La fecha hasta debe tener un formato válido.',
            'clasifica_documen_id.exists' => 'La clasificación documental seleccionada no existe.',
            'tercero_enviado_id.exists' => 'El destinatario seleccionado no existe.',
            'medio_enviado_id.exists' => 'El medio de envío seleccionado no existe.',
            'usuario_responsable.exists' => 'El usuario responsable seleccionado no existe.',
            'per_page.integer' => 'El número de elementos por página debe ser un número entero.',
            'per_page.min' => 'El número de elementos por página debe ser al menos 1.',
            'per_page.max' => 'El número de elementos por página no puede superar 100.',
        ];
    }
}
