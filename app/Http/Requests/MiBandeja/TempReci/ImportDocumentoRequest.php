<?php

namespace App\Http\Requests\MiBandeja\TempReci;

use Illuminate\Foundation\Http\FormRequest;

class ImportDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => 'nullable|file|mimes:docx,doc,html,htm,txt|max:10240',
            'contenido' => 'nullable|string',
            'titulo' => 'nullable|string|max:255',
            'radica_reci_id' => 'nullable|exists:ventanilla_radica_reci,id',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.file' => 'Debe subir un archivo válido',
            'archivo.mimes' => 'Formato no permitido. Use: docx, doc, html, htm, txt',
            'archivo.max' => 'El archivo debe ser menor a 10MB',
            'radica_reci_id.exists' => 'El radicado seleccionado no existe',
        ];
    }
}
