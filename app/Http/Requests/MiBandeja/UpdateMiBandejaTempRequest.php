<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud HTTP para actualizar un grupo colaborativo temporal.
 * Valida los datos para la actualización de un grupo en Mi Bandeja.
 */
class UpdateMiBandejaTempRequest extends FormRequest
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
     * Reglas de validación para la actualización de un grupo colaborativo temporal.
     *
     * @return array<string, mixed> Arreglo de reglas de validación
     */
    public function rules(): array
    {
        $radicadoId = $this->input('radicado_id');
        $tablaRadicado = match ($this->input('radicado_tipo')) {
            'enviado' => 'ventanilla_radica_enviados',
            'interno' => 'ventanilla_radica_internos',
            default => 'ventanilla_radica_reci',
        };

        return [
            'nombre' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'radicado_id' => [
                'nullable',
                'integer',
                $radicadoId ? "exists:{$tablaRadicado},id" : 'nullable',
            ],
            'radicado_tipo' => ['nullable', Rule::in(['recibido', 'enviado', 'interno'])],
            'estado' => ['nullable', Rule::in(['borrador', 'activo', 'finalizado', 'archivado'])],
            'estado_grupo' => ['nullable', Rule::in(['activo', 'inactivo', 'anulado'])],
            'asunto' => 'nullable|string|max:255',
            'con_copia' => 'nullable|array',
            'anexos' => 'nullable|array',
            'plantilla_cargada' => 'nullable|boolean',
            'plantilla_id' => 'nullable|integer|exists:ofi_archivo_plantillas_documentos,id',
        ];
    }
}
