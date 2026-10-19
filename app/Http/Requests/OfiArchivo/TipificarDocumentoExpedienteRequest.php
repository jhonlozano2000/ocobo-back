<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

class TipificarDocumentoExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documental_id' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documental_id.required' => 'Debe elegir el tipo documental.',
            'tipo_documental_id.integer' => 'El tipo documental no es válido.',
        ];
    }
}
