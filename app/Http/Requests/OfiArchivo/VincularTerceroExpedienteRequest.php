<?php

namespace App\Http\Requests\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpedienteTercero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * B24: vínculo de un tercero con un expediente, con su rol en el trámite.
 */
class VincularTerceroExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tercero_id' => 'required|integer|exists:gestion_terceros,id',
            'rol' => ['required', 'string', Rule::in(OfiArchivoExpedienteTercero::ROLES)],
        ];
    }

    public function messages(): array
    {
        return [
            'tercero_id.required' => 'Seleccione el tercero.',
            'tercero_id.exists' => 'El tercero no existe.',
            'rol.required' => 'Indique el rol del tercero en el expediente.',
            'rol.in' => 'Rol no válido. Use: '.implode(', ', OfiArchivoExpedienteTercero::ROLES).'.',
        ];
    }
}
