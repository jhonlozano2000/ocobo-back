<?php

namespace App\Http\Requests\Ventanilla\Pqrs;

use Illuminate\Foundation\Http\FormRequest;

class UploadPqrsAdjuntosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivos' => [
                'required',
                'array',
                'max:10',
            ],
            'archivos.*' => [
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivos.required' => 'Debe seleccionar al menos un archivo.',
            'archivos.array' => 'Los archivos deben ser un arreglo.',
            'archivos.max' => 'Máximo 10 archivos por vez.',
            'archivos.*.file' => 'Cada elemento debe ser un archivo válido.',
            'archivos.*.max' => 'Cada archivo no debe exceder 50MB.',
            'archivos.*.mimes' => 'Tipo de archivo no permitido.',
        ];
    }
}
