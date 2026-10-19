<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B25: cierre del expediente (Acuerdo AGN 001/2024). El motivo queda registrado en el expediente.
 */
class CerrarExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_cierre' => 'required|string|max:500',
            // B14: fecha en que terminó el trámite; por defecto hoy
            'fecha_cierre' => 'nullable|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_cierre.required' => 'El motivo del cierre es obligatorio.',
            'motivo_cierre.max' => 'El motivo del cierre no puede superar los 500 caracteres.',
            'fecha_cierre.date' => 'La fecha de cierre no es válida.',
            'fecha_cierre.before_or_equal' => 'La fecha de cierre no puede ser futura.',
        ];
    }
}
