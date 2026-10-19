<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B25: incorporación de un radicado al expediente.
 */
class IncorporarDocumentoExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documentable_id' => 'required|integer',
            'documentable_type' => 'required|string|in:radicado_recibido,radicado_enviado',
            'detalle' => 'nullable|string|max:500',
            'tipo_documental_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'documentable_id.required' => 'Debe indicar el radicado a incorporar.',
            'documentable_type.required' => 'Debe indicar el tipo de radicado.',
            'documentable_type.in' => 'Solo se pueden incorporar radicados recibidos o enviados.',
            'detalle.max' => 'El detalle no puede superar los 500 caracteres.',
        ];
    }
}
