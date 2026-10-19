<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B13: reapertura de un expediente cerrado (diseño de expedientes §3.5). El motivo es obligatorio.
 */
class ReabrirExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_reapertura' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_reapertura.required' => 'El motivo de la reapertura es obligatorio.',
            'motivo_reapertura.max' => 'El motivo de la reapertura no puede superar los 500 caracteres.',
        ];
    }
}
