<?php

namespace App\Http\Requests\Ventanilla\Internos;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Configuracion\ConfigVarias;

class UploadArchivoInternoRequest extends FormRequest
{
    private static ?array $configCache = null;

    private function getConfigValues(): array
    {
        if (self::$configCache === null) {
            self::$configCache = [
                'maxSize' => ConfigVarias::getValor('max_tamano_archivo', 20480),
                'allowedExtensions' => ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,jpg,png,docx'),
            ];
        }
        return self::$configCache;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $config = $this->getConfigValues();
        $allowedExtensions = explode(',', $config['allowedExtensions']);

        return [
            'archivo_digital' => [
                'required',
                'file',
                'max:' . $config['maxSize'],
                'mimes:' . implode(',', $allowedExtensions)
            ]
        ];
    }

    public function messages(): array
    {
        $config = $this->getConfigValues();
        return [
            'archivo_digital.required' => 'El archivo es obligatorio.',
            'archivo_digital.file' => 'El archivo debe ser un archivo válido.',
            'archivo_digital.max' => "El archivo no puede superar los {$config['maxSize']} KB.",
            'archivo_digital.mimes' => "El archivo debe ser de tipo: {$config['allowedExtensions']}."
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo_digital' => 'archivo'
        ];
    }
}