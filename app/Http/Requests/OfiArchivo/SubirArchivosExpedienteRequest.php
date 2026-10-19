<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B25: carga directa de archivos al expediente (por tipo documental o expediente completo).
 */
class SubirArchivosExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // M08-17: tope de cantidad; 50 MB por archivo sin límite de archivos permitía cargas enormes
            'archivos' => 'required|array|min:1|max:20',
            'archivos.*' => 'file|max:51200',
            'tipo' => 'required|in:tipo_documental,expediente_completo',
            'tipo_documental' => 'nullable|required_if:tipo,tipo_documental|string|max:200',
            'descripcion' => 'nullable|string|max:500',
            'detalle' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'archivos.required' => 'Debe seleccionar al menos un archivo.',
            'archivos.max' => 'No se pueden subir más de 20 archivos a la vez.',
            'archivos.*.file' => 'Uno de los elementos enviados no es un archivo válido.',
            'archivos.*.max' => 'Cada archivo puede pesar como máximo 50 MB.',
            'tipo.required' => 'Debe indicar el tipo de carga.',
            'tipo.in' => 'El tipo de carga debe ser tipo documental o expediente completo.',
            'tipo_documental.required_if' => 'El nombre del tipo documental es requerido.',
            'tipo_documental.max' => 'El tipo documental no puede superar los 200 caracteres.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
        ];
    }
}
