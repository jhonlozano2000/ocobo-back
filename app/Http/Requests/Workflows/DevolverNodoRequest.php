<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use Illuminate\Support\Facades\Auth;

/** M19-28: devolver la instancia desde un paso en curso a un paso anterior, con motivo. */
class DevolverNodoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasPermissionTo('Workflows -> Instancias -> Ejecutar');
    }

    public function rules(): array
    {
        return [
            'nodo_origen_id' => 'required|integer|different:nodo_destino_id',
            'nodo_destino_id' => 'required|integer',
            'motivo' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indique el motivo de la devolución',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres',
            'nodo_origen_id.different' => 'El paso de destino debe ser distinto del paso actual',
        ];
    }
}
