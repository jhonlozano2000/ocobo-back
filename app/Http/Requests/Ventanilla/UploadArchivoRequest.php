<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Configuracion\ConfigVarias;

class UploadArchivoRequest extends FormRequest
{
    /**
     * Valores de configuración cacheados para evitar múltiples consultas.
     *
     * @var array|null
     */
    private static ?array $configCache = null;

    /**
     * Obtiene los valores de configuración (cacheados para evitar múltiples consultas).
     *
     * @return array
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $config = $this->getConfigValues();
        $allowedExtensions = explode(',', $config['allowedExtensions']);

        return [
            'archivo' => [
                'required',
                'file',
                'max:' . $config['maxSize'],
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
        $config = $this->getConfigValues();

        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.max' => "El archivo no puede superar los {$config['maxSize']} KB.",
            'archivo.mimes' => "El archivo debe ser de tipo: {$config['allowedExtensions']}."
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
