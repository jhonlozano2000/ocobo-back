<?php

namespace App\Http\Requests\OfiArchivo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * B25: apertura de expediente. El permiso lo valida la ruta y la dependencia/serie el controlador.
 */
class StoreExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_expediente' => 'required|string|max:300',
            'dependencia_id' => 'required|exists:calidad_organigrama,id',
            'serie_trd_id' => 'required|exists:clasificacion_documental_trd,id',
            // B06: se puede enviar la SubSerie en serie_trd_id (árbol de la TRD) o en su propio campo
            'subserie_trd_id' => 'nullable|integer|exists:clasificacion_documental_trd,id',
            'soporte' => 'required|in:electronico,fisico,hibrido',
            'nivel_acceso' => 'required|in:publico,clasificado,reservado',
            'fundamento_acceso' => 'nullable|required_unless:nivel_acceso,publico|string|min:10|max:500',
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
            'nombre_expediente.required' => 'El nombre del expediente es obligatorio.',
            'nombre_expediente.max' => 'El nombre del expediente no puede superar los 300 caracteres.',
            'dependencia_id.required' => 'La dependencia es obligatoria.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
            'serie_trd_id.required' => 'La serie o subserie de la TRD es obligatoria.',
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
