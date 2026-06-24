<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud HTTP para agregar un responsable a un grupo colaborativo temporal.
 * Valida los datos requeridos para la creación de un responsable.
 */
class StoreGrupoResponsableRequest extends FormRequest
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
     * Reglas de validación para agregar un responsable a un grupo.
     *
     * @return array<string, mixed> Arreglo de reglas de validación
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'cargo_id' => 'nullable|integer|exists:calidad_organigrama,id',
            'es_custodio' => 'nullable|boolean',
        ];
    }
}