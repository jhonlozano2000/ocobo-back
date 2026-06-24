<?php

namespace App\Http\Requests\MiBandeja;

use App\Rules\MagicMime;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:docx,doc,pdf,odt,dotx,xlsx,xls,pptx,ppt,txt,csv,jpeg,jpg,png,gif', 'max:51200', new MagicMime],
            'comentario' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo para subir.',
            'archivo.max' => 'El archivo no puede superar los 50MB.',
            'archivo.mimes' => 'El formato del archivo no está permitido.',
        ];
    }
}
