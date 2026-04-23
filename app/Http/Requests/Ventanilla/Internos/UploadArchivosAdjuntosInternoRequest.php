<?php

namespace App\Http\Requests\Ventanilla\Internos;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Configuracion\ConfigVarias;

class UploadArchivosAdjuntosInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 10240);

        return [
            'archivos' => 'required|array|min:1|max:10',
            'archivos.*' => [
                'file',
                'max:' . $maxSize
            ]
        ];
    }

    public function messages(): array
    {
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 10240);
        return [
            'archivos.required' => 'Los archivos son obligatorios.',
            'archivos.array' => 'Los archivos deben ser enviados como un array.',
            'archivos.min' => 'Debe enviar al menos un archivo.',
            'archivos.max' => 'No puede enviar más de 10 archivos a la vez.',
            'archivos.*.required' => 'Cada archivo es obligatorio.',
            'archivos.*.file' => 'Cada elemento debe ser un archivo válido.',
            'archivos.*.max' => "Cada archivo no puede superar los {$maxSize} KB."
        ];
    }

    public function attributes(): array
    {
        return [
            'archivos' => 'archivos adjuntos',
            'archivos.*' => 'archivo'
        ];
    }
}