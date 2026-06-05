<?php

namespace App\Http\Requests\Ventanilla\Pqrs;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePqrsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observaciones' => 'nullable|string|max:5000',
            'prioridad' => 'nullable|in:Normal,Urgente,Tutela',
            'fallo_judicial' => 'nullable|in:Si,No',
            'tipo_pqrs_id' => 'nullable|exists:config_listas_detalles,id',
            'clasificacion_documental_trd_id' => 'nullable|exists:clasificacion_documental_trd,id',
            'modalidad' => 'nullable|string|max:100',
            'derecho_solicitado' => 'nullable|string|max:255',
            'area_afectada' => 'nullable|string|max:255',
            'funcionarios_implicados' => 'nullable|string|max:1000',
            'derecho_vulnerado' => 'nullable|string|max:255',
            'pretension' => 'nullable|string|max:2000',
            'area_mejora' => 'nullable|string|max:2000',
            'motivo_felicitacion' => 'nullable|string|max:2000',
            'autoridad_destino' => 'nullable|string|max:255',
            'tipo_persona' => 'nullable|in:Natural,Jurídica',
        ];
    }

    public function messages(): array
    {
        return [
            'prioridad.in' => 'La prioridad debe ser Normal, Urgente o Tutela.',
            'fallo_judicial.in' => 'El fallo judicial debe ser Sí o No.',
            'tipo_pqrs_id.exists' => 'El tipo de PQRS seleccionado no existe.',
            'clasificacion_documental_trd_id.exists' => 'La clasificación documental seleccionada no existe.',
            'funcionarios_implicados.max' => 'La lista de funcionarios no puede superar los 1000 caracteres.',
            'derecho_vulnerado.max' => 'El derecho vulnerado no puede superar los 255 caracteres.',
            'pretension.max' => 'La pretensión no puede superar los 2000 caracteres.',
            'area_mejora.max' => 'El área de mejora no puede superar los 2000 caracteres.',
            'motivo_felicitacion.max' => 'El motivo de felicitación no puede superar los 2000 caracteres.',
        ];
    }
}
