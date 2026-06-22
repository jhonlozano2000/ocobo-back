<?php

namespace App\Http\Requests\Ventanilla\Pqrs;

use Illuminate\Foundation\Http\FormRequest;

class UploadPqrsDigitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo_digital' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo_digital.required' => 'El archivo digital es obligatorio.',
            'archivo_digital.file' => 'Debe ser un archivo válido.',
            'archivo_digital.max' => 'El archivo no debe exceder 50MB.',
            'archivo_digital.mimes' => 'Tipo de archivo no permitido.',
        ];
    }
}
