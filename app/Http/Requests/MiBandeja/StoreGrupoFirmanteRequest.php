<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud HTTP para agregar un firmante a un grupo colaborativo temporal.
 * Valida los datos requeridos para la creación de un firmante.
 */
class StoreGrupoFirmanteRequest extends FormRequest
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
     * Reglas de validación para agregar un firmante a un grupo.
     *
     * @return array<string, mixed> Arreglo de reglas de validación
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'cargo_id' => 'nullable|integer|exists:calidad_organigrama,id',
            'orden_firma' => 'nullable|integer|min:1',
        ];
    }
}