<?php

namespace App\Http\Requests\ClasificacionDocumental;

use Illuminate\Foundation\Http\FormRequest;

class AprobarTRDVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'versionId' => 'required|exists:clasificacion_documental_trd_versiones,id',
            'observaciones' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'versionId.required' => 'El ID de la versión es requerido.',
            'versionId.exists' => 'La versión no existe.',
            'observaciones.required' => 'Debe proporcionar una observación.',
            'observaciones.string' => 'Las observaciones deben ser un texto válido.',
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}
