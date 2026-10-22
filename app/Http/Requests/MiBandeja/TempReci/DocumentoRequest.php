<?php

namespace App\Http\Requests\MiBandeja\TempReci;

use App\Rules\RadicadoAccesible;
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
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'radica_reci_id' => [
                'nullable',
                // Solo radicados a los que el usuario tiene acceso; al editar se puede conservar el ya vinculado
                new RadicadoAccesible('recibido', $this->route('documento')?->radica_reci_id),
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
                // «firmado» solo con la firma OTP (documentos/{id}/firmas), que además protege el contenido
                Rule::in(['borrador', 'en_revision']),
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
            // Solo al crear: plantilla generada (institucional publicada o personal) para el contenido inicial
            'plantilla_id' => [
                'nullable',
                'integer',
                Rule::exists('ofi_archivo_plantillas_generadas', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($q) => $q->where(fn ($i) => $i->where('institucional', true)->where('activo', true))
                        ->orWhere(fn ($p) => $p->where('institucional', false)->where('user_id', $this->user()?->id))),
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
            'plantilla_id.exists' => 'La plantilla no es válida.',
            'titulo.min' => 'El título debe tener al menos 3 caracteres.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'estado.in' => 'El estado debe ser: borrador o en_revision. Para firmar use la firma con código OTP.',
            'notas.max' => 'Las notas no pueden exceder 5000 caracteres.',
        ];
    }
}
