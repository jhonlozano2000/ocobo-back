<?php

namespace App\Http\Requests\Ventanilla\Pqrs;

use App\Models\Configuracion\ConfigVarias;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de subida del archivo digital principal para radicados PQRS.
 *
 * El tamaño máximo y las extensiones permitidas se leen desde la tabla
 * config_varias (claves max_tamano_archivo y tipos_archivos_permitidos),
 * siguiendo el mismo patrón de los módulos Recibidos/Enviados/Internos.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-23
 */
class UploadPqrsDigitalRequest extends FormRequest
{
    /**
     * Valores de configuración cacheados para evitar múltiples consultas.
     */
    private static ?array $configCache = null;

    /**
     * Obtiene los valores de configuración (cacheados para evitar múltiples consultas).
     *
     * @return array{maxSize: int, allowedExtensions: string}
     */
    private function getConfigValues(): array
    {
        if (self::$configCache === null) {
            self::$configCache = [
                'maxSize' => ConfigVarias::getValor('max_tamano_archivo', 51200), // 50MB por defecto
                'allowedExtensions' => ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif'),
            ];
        }

        return self::$configCache;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
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
            'descripcion' => ['nullable', 'string', 'max:500'],
            'palabras_clave' => ['nullable', 'string', 'max:500'],
            'clasificacion_id' => ['nullable', 'integer', 'exists:clasificacion_documental_trd,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $config = $this->getConfigValues();

        return [
            'archivo_digital.required' => 'El archivo digital es obligatorio.',
            'archivo_digital.file' => 'Debe ser un archivo válido.',
            'archivo_digital.max' => "El archivo no puede superar los {$config['maxSize']} KB.",
            'archivo_digital.mimes' => "El tipo de archivo no está permitido. Extensiones válidas: {$config['allowedExtensions']}.",
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'archivo_digital' => 'archivo',
        ];
    }
}
