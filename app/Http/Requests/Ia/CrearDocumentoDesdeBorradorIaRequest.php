<?php

namespace App\Http\Requests\Ia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CrearDocumentoDesdeBorradorIaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para crear un documento colaborativo desde un borrador de IA.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulo' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],
            'borrador' => [
                'required',
                'string',
                'min:10',
            ],
            'radica_reci_id' => [
                'nullable',
                'integer',
                'exists:ventanilla_radica_reci,id',
            ],
            'plantilla_id' => [
                'nullable',
                'integer',
                'exists:ofi_archivo_plantillas_generadas,id',
            ],
            'notas' => [
                'nullable',
                'string',
                'max:500',
            ],
            'auditoria_id' => [
                'nullable',
                'integer',
                'exists:ia_borradores_auditoria,id',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título del documento es obligatorio.',
            'titulo.min' => 'El título debe tener al menos 3 caracteres.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'borrador.required' => 'El texto del borrador generado por la IA es obligatorio.',
            'borrador.min' => 'El borrador debe contener al menos 10 caracteres.',
            'radica_reci_id.exists' => 'El radicado recibido especificado no existe.',
            'plantilla_id.exists' => 'La plantilla institucional especificada no existe.',
        ];
    }

    /**
     * Atributos personalizados para los campos de validación.
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título del documento',
            'borrador' => 'texto del borrador',
            'radica_reci_id' => 'radicado recibido',
            'plantilla_id' => 'plantilla institucional',
            'notas' => 'notas del documento',
            'auditoria_id' => 'registro de auditoría',
        ];
    }
}
