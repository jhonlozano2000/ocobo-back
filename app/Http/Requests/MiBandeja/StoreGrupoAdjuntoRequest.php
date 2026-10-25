<?php

namespace App\Http\Requests\MiBandeja;

use App\Models\Configuracion\ConfigVarias;
use App\Rules\ArchivoLegible;
use App\Rules\ArchivoSeguro;
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
        return [
            'archivo' => [
                'bail',
                'required',
                'file',
                new ArchivoLegible,
                'max:'.$this->maxSizeKB(), // la regla max de archivos se expresa en KB
                new ArchivoSeguro,
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
        $maxSizeKB = $this->maxSizeKB();

        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.max' => "El archivo no puede superar los {$maxSizeKB} KB.",
        ];
    }

    /**
     * Tamaño máximo en KB (ConfigVarias max_tamano_archivo).
     */
    private function maxSizeKB(): int
    {
        return (int) ConfigVarias::getValor('max_tamano_archivo', 10240);
    }
}
