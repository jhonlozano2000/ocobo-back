<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Configuracion\ConfigVarias;

class UploadArchivoRequest extends FormRequest
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
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 20480); // 20MB por defecto
        $allowedExtensions = explode(',', ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,jpg,png,docx'));

        return [
            'archivo' => [
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
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 20480);
        $allowedExtensions = ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,jpg,png,docx');

        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.max' => "El archivo no puede superar los {$maxSize} KB.",
            'archivo.mimes' => "El archivo debe ser de tipo: {$allowedExtensions}."
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
            'archivo' => 'archivo'
        ];
    }
}
