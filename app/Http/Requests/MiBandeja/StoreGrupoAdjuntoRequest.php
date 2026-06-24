<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud HTTP para subir un adjunto a un grupo colaborativo temporal.
 * Valida los datos requeridos para la subida de archivos.
 */
class StoreGrupoAdjuntoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * @return bool true si está autorizado, false de lo contrario
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para subir un adjunto a un grupo.
     *
     * @return array<string, mixed> Arreglo de reglas de validación
     */
    public function rules(): array
    {
        $maxSize = config('config_varias.max_tamano_archivo', 10240) * 1024; // KB a bytes

        return [
            'archivo' => [
                'required',
                'file',
                'max:' . $maxSize,
            ],
            'tipo' => ['nullable', Rule::in(['respuesta', 'anexo', 'evidencia'])],
        ];
    }

    /**
     * Mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string> Arreglo de mensajes de error
     */
    public function messages(): array
    {
        $maxSizeKB = config('config_varias.max_tamano_archivo', 10240);
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.max' => "El archivo no puede superar los {$maxSizeKB} KB.",
        ];
    }
}