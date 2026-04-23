<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurarTiposDocumentalesRequest extends FormRequest
{
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
        return [
            'tipos_documentales' => [
                'required',
                'array',
                'min:1'
            ],
            'tipos_documentales.*' => [
                'required',
                'integer',
                'exists:clasificacion_documental_trd,id'
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
        return [
            'tipos_documentales.required' => 'Los tipos documentales son obligatorios.',
            'tipos_documentales.array' => 'Los tipos documentales deben ser un arreglo.',
            'tipos_documentales.min' => 'Debe seleccionar al menos un tipo documental.',
            'tipos_documentales.*.required' => 'Cada tipo documental es obligatorio.',
            'tipos_documentales.*.integer' => 'Cada tipo documental debe ser un número entero.',
            'tipos_documentales.*.exists' => 'Uno o más tipos documentales no existen.'
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
            'tipos_documentales' => 'tipos documentales',
            'tipos_documentales.*' => 'tipo documental'
        ];
    }
}
