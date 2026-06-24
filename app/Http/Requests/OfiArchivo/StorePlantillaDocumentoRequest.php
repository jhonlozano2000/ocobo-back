<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

class StorePlantillaDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:docx,doc,pdf,odt,dotx,ott,xlsx,xls,ppt,pptx,txt,rtf,csv',
                'max:10240',
            ],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'version' => 'nullable|string|max:10',
            'fecha_vencimiento' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo para subir.',
            'archivo.mimes' => 'Solo se permiten archivos: docx, doc, pdf, odt, dotx, ott, xlsx, xls, ppt, pptx, txt, rtf, csv.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
            'nombre.required' => 'El nombre de la plantilla es obligatorio.',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser futura.',
        ];
    }
}
