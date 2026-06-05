<?php

namespace App\Http\Requests\Ventanilla\Pqrs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ListPqrsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasPermissionTo('Radicar -> PQRSF -> Listar');
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'tipo_pqrs_id' => 'nullable|exists:config_listas_detalles,id',
            'estado_tramite' => 'nullable|in:Pendiente,En Tramite,Respondida,Vencida',
            'prioridad' => 'nullable|in:Normal,Urgente,Tutela',
            'clasificacion_id' => 'nullable|exists:clasificacion_documental_trd,id',
            'gestion_tercero_id' => 'nullable|exists:gestion_terceros,id',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_pqrs_id.exists' => 'El tipo de PQRS seleccionado no es válido.',
            'estado_tramite.in' => 'El estado del trámite debe ser Pendiente, En Tramite, Respondida o Vencida.',
            'prioridad.in' => 'La prioridad debe ser Normal, Urgente o Tutela.',
            'clasificacion_id.exists' => 'La clasificación documental seleccionada no es válida.',
            'gestion_tercero_id.exists' => 'El tercero/afectado seleccionado no es válido.',
            'fecha_desde.date' => 'La fecha desde debe ser una fecha válida.',
            'fecha_hasta.date' => 'La fecha hasta debe ser una fecha válida.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'per_page.integer' => 'El número de elementos por página debe ser un entero.',
            'per_page.min' => 'El número de elementos por página debe ser al menos 1.',
            'per_page.max' => 'El número de elementos por página no puede superar 100.',
        ];
    }
}