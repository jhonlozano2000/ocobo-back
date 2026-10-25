<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use App\Rules\ArchivoLegible;
use App\Rules\ArchivoSeguro;

/**
 * M19-35: evidencia adjunta a una Tarea (Mis tareas).
 */
class StoreTareaArchivoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public const EXTENSIONES = ['pdf', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];

    public function rules(): array
    {
        return [
            'archivo' => ['bail', 'required', 'file', new ArchivoLegible, 'max:51200', new ArchivoSeguro(self::EXTENSIONES), 'mimes:'.implode(',', self::EXTENSIONES)],
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
