<?php

namespace App\Http\Requests\Ventanilla\Recibidos;

use App\Models\Configuracion\ConfigVarias;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadArchivoRecibidoRequest extends FormRequest
{
    /**
     * Valores de configuración cacheados para evitar múltiples consultas.
     */
    private static ?array $configCache = null;

    /**
     * Obtiene los valores de configuración (cacheados para evitar múltiples consultas).
     */
    private function getConfigValues(): array
    {
        if (self::$configCache === null) {
            self::$configCache = [
                'maxSize' => ConfigVarias::getValor('max_tamano_archivo', 20480), // 20MB por defecto
                'allowedExtensions' => ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,jpg,png,docx'),
            ];
        }

        return self::$configCache;
    }

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $config = $this->getConfigValues();
        $allowedExtensions = explode(',', $config['allowedExtensions']);

        return [
            'archivo_digital' => [
                'required',
                'file',
                'max:'.$config['maxSize'],
                'mimes:'.implode(',', $allowedExtensions),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $config = $this->getConfigValues();

        return [
            'archivo_digital.required' => 'El archivo es obligatorio.',
            'archivo_digital.file' => 'El archivo debe ser un archivo válido.',
            'archivo_digital.max' => "El archivo no puede superar los {$config['maxSize']} KB.",
            'archivo.mimes' => "El archivo debe ser de tipo: {$config['allowedExtensions']}.",
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'archivo_digital' => 'archivo',
        ];
    }
}
