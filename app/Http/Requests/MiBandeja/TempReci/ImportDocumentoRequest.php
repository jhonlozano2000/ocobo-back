<?php

namespace App\Http\Requests\MiBandeja\TempReci;

use App\Rules\ArchivoLegible;
use App\Rules\RadicadoAccesible;
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
            'archivo' => ['bail', 'nullable', 'file', new ArchivoLegible, 'mimes:docx,doc,html,htm,txt', 'max:10240'],
            'contenido' => 'nullable|string',
            'titulo' => 'nullable|string|max:255',
            'radica_reci_id' => ['nullable', new RadicadoAccesible('recibido')],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.file' => 'Debe subir un archivo válido',
            'archivo.mimes' => 'Formato no permitido. Use: docx, doc, html, htm, txt',
            'archivo.max' => 'El archivo debe ser menor a 10MB',
        ];
    }
}
