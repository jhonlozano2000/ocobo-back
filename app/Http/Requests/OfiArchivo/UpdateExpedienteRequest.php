<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B25: edición de un expediente abierto. El controlador revalida dependencia/serie.
 */
class UpdateExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_expediente' => 'nullable|string|max:300',
            'dependencia_id' => 'nullable|exists:calidad_organigrama,id',
            'serie_trd_id' => 'nullable|exists:clasificacion_documental_trd,id',
            'subserie_trd_id' => 'nullable|integer|exists:clasificacion_documental_trd,id',
            'soporte' => 'nullable|in:electronico,fisico,hibrido',
            'nivel_acceso' => 'nullable|in:publico,clasificado,reservado',
            'fundamento_acceso' => 'nullable|required_if:nivel_acceso,clasificado,reservado|string|min:10|max:500',
            'ubicacion_fisica' => 'nullable|string|max:200',
            'caja' => 'nullable|string|max:50',
            'carpeta' => 'nullable|string|max:50',
            'folios_fisicos' => 'nullable|integer|min:0',
            'observaciones_generales' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_expediente.max' => 'El nombre del expediente no puede superar los 300 caracteres.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
            'serie_trd_id.exists' => 'La serie o subserie seleccionada no existe.',
            'subserie_trd_id.exists' => 'La subserie seleccionada no existe.',
            'soporte.required' => 'El soporte del expediente es obligatorio.',
            'soporte.in' => 'El soporte debe ser electrónico, físico o híbrido.',
            'nivel_acceso.required' => 'El nivel de acceso es obligatorio.',
            'nivel_acceso.in' => 'El nivel de acceso debe ser público, clasificado o reservado.',
            'fundamento_acceso.required_unless' => 'Indique el fundamento legal de la clasificación o reserva.',
            'fundamento_acceso.required_if' => 'Indique el fundamento legal de la clasificación o reserva.',
            'fundamento_acceso.min' => 'El fundamento legal debe tener al menos 10 caracteres.',
            'folios_fisicos.integer' => 'Los folios físicos deben ser un número entero.',
            'folios_fisicos.min' => 'Los folios físicos no pueden ser negativos.',
        ];
    }
}
