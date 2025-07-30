<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class FinalizarCargoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ajustar según los permisos del sistema
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:' . now()->subYears(2)->format('Y-m-d'),
                'before_or_equal:' . now()->format('Y-m-d')
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'fecha_fin.date' => 'La fecha de finalización debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de finalización no puede ser anterior a 2 años.',
            'fecha_fin.before_or_equal' => 'La fecha de finalización no puede ser posterior a hoy.',

            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden tener más de 500 caracteres.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Establecer fecha de fin por defecto si no se proporciona
        if (!$this->has('fecha_fin') || empty($this->fecha_fin)) {
            $this->merge([
                'fecha_fin' => now()->format('Y-m-d')
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'fecha_fin' => 'fecha de finalización',
            'observaciones' => 'observaciones'
        ];
    }
}
