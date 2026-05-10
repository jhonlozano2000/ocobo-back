<?php

namespace App\Http\Requests\MiBandeja\TempReci;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de validación para documentos colaborativos.
 *
 * Valida los datos para crear o actualizar documentos
 * de comunicaciones recibidas en Mi Bandeja.
 */
class DocumentoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, \Illuminate\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'radica_reci_id' => [
                'nullable',
                'exists:ventanilla_radica_reci,id',
            ],
            'titulo' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],
            'estado' => [
                'sometimes',
                'string',
                Rule::in(['borrador', 'en_revision', 'firmado']),
            ],
            'notas' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'es_publico' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'radica_reci_id.required' => 'Debe seleccionar un radicado.',
            'radica_reci_id.exists' => 'El radicado seleccionado no existe.',
            'titulo.required' => 'El título es obligatorio.',
            'titulo.min' => 'El título debe tener al menos 3 caracteres.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'estado.in' => 'El estado debe ser: borrador, en_revision o firmado.',
            'notas.max' => 'Las notas no pueden exceder 5000 caracteres.',
        ];
    }
}