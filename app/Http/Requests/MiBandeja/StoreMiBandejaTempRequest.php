<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud HTTP para crear un grupo colaborativo temporal.
 * Valida los datos requeridos para la creación de un grupo en Mi Bandeja.
 */
class StoreMiBandejaTempRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * @return bool true si está autorizado, false de lo contrario
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la creación de un grupo colaborativo temporal.
     *
     * @return array<string, mixed> Arreglo de reglas de validación
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'radicado_id' => 'required|integer|exists:ventanilla_radica_reci,id',
            'radicado_tipo' => ['required', Rule::in(['recibido', 'enviado', 'interno'])],
            'estado' => ['nullable', Rule::in(['borrador', 'activo', 'finalizado', 'archivado'])],
            'estado_grupo' => ['nullable', Rule::in(['activo', 'inactivo', 'anulado'])],
            'asunto' => 'nullable|string|max:255',
            'con_copia' => 'nullable|array',
            'anexos' => 'nullable|array',
            'plantilla_cargada' => 'nullable|boolean',
            'plantilla_id' => 'nullable|integer|exists:ofi_archivo_plantillas_documentos,id',
        ];
    }

    /**
     * Mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string> Arreglo de mensajes de error
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del grupo es obligatorio.',
            'radicado_id.required' => 'El radicado es obligatorio.',
            'radicado_id.exists' => 'El radicado seleccionado no existe.',
            'radicado_tipo.required' => 'El tipo de radicado es obligatorio.',
            'radicado_tipo.in' => 'El tipo de radicado no es válido.',
        ];
    }
}