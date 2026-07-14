<?php

namespace App\Http\Requests\Transversal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class FirmaValidarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'documentable_id' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'documentable_type.required' => 'El tipo de documento es obligatorio.',
            'documentable_type.in' => 'El tipo de documento no es válido.',
            'documentable_id.required' => 'El ID del documento es obligatorio.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'status' => false,
            'message' => 'Errores de validación.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
