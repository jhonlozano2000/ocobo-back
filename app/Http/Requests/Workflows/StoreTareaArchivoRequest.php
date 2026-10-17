<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;

/**
 * M19-35: evidencia adjunta a una Tarea (Mis tareas).
 */
class StoreTareaArchivoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => 'required|file|max:51200|mimes:pdf,jpeg,png,gif,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
            'categoria' => 'nullable|in:adjunto,evidencia,resultado',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo',
            'archivo.max' => 'El archivo no puede exceder 50MB',
            'archivo.mimes' => 'El tipo de archivo no está permitido',
        ];
    }
}
