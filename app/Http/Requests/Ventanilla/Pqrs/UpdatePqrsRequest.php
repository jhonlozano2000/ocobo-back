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
            'observaciones.string' => 'Las observaciones deben ser un texto válido.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
            'prioridad.in' => 'La prioridad debe ser Normal, Urgente o Tutela.',
            'fallo_judicial.in' => 'El fallo judicial debe ser Sí o No.',
            'tipo_pqrs_id.exists' => 'El tipo de PQRS seleccionado no existe.',
            'clasificacion_documental_trd_id.exists' => 'La clasificación documental seleccionada no existe.',
            'modalidad.string' => 'La modalidad debe ser un texto válido.',
            'modalidad.max' => 'La modalidad no puede exceder los 100 caracteres.',
            'derecho_solicitado.string' => 'El derecho solicitado debe ser un texto válido.',
            'derecho_solicitado.max' => 'El derecho solicitado no puede exceder los 255 caracteres.',
            'area_afectada.string' => 'El área afectada debe ser un texto válido.',
            'area_afectada.max' => 'El área afectada no puede exceder los 255 caracteres.',
            'funcionarios_implicados.string' => 'Los funcionarios implicados deben ser un texto válido.',
            'funcionarios_implicados.max' => 'La lista de funcionarios no puede superar los 1000 caracteres.',
            'derecho_vulnerado.string' => 'El derecho vulnerado debe ser un texto válido.',
            'derecho_vulnerado.max' => 'El derecho vulnerado no puede superar los 255 caracteres.',
            'pretension.string' => 'La pretensión debe ser un texto válido.',
            'pretension.max' => 'La pretensión no puede superar los 2000 caracteres.',
            'area_mejora.string' => 'El área de mejora debe ser un texto válido.',
            'area_mejora.max' => 'El área de mejora no puede superar los 2000 caracteres.',
            'motivo_felicitacion.string' => 'El motivo de felicitación debe ser un texto válido.',
            'motivo_felicitacion.max' => 'El motivo de felicitación no puede superar los 2000 caracteres.',
            'autoridad_destino.string' => 'La autoridad de destino debe ser un texto válido.',
            'autoridad_destino.max' => 'La autoridad de destino no puede exceder los 255 caracteres.',
            'tipo_persona.in' => 'El tipo de persona debe ser Natural o Jurídica.',
        ];
    }
}
