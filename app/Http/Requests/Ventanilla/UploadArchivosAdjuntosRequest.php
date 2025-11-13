<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Configuracion\ConfigVarias;

class UploadArchivosAdjuntosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // La autorización se maneja a través de middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 10240); // 10MB por defecto para archivos adjuntos
        $allowedExtensions = explode(',', ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,doc,docx,jpg,jpeg,png,gif'));

        return [
            'archivos' => 'required|array|min:1|max:10', // Máximo 10 archivos
            'archivos.*' => [
                'required',
                'file',
                'max:' . $maxSize,
                'mimes:' . implode(',', $allowedExtensions)
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 10240);
        $allowedExtensions = ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,doc,docx,jpg,jpeg,png,gif');

        return [
            'archivos.required' => 'Los archivos son obligatorios.',
            'archivos.array' => 'Los archivos deben ser enviados como un array.',
            'archivos.min' => 'Debe enviar al menos un archivo.',
            'archivos.max' => 'No puede enviar más de 10 archivos a la vez.',
            'archivos.*.required' => 'Cada archivo es obligatorio.',
            'archivos.*.file' => 'Cada elemento debe ser un archivo válido.',
            'archivos.*.max' => "Cada archivo no puede superar los {$maxSize} KB.",
            'archivos.*.mimes' => "Cada archivo debe ser de tipo: {$allowedExtensions}."
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'archivos' => 'archivos adjuntos',
            'archivos.*' => 'archivo adjunto'
        ];
    }
}