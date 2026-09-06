<?php

namespace App\Http\Requests\Ventanilla\Pqrs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePqrsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->hasPermissionTo('Radicar -> PQRSF -> Crear');
    }

    public function rules(): array
    {
        $rules = [
            'ventanilla_radica_reci_id' => 'nullable|exists:ventanilla_radica_reci,id',
            'tipo_pqrs_id' => 'required|exists:config_listas_detalles,id',
            'gestion_tercero_id' => 'nullable|exists:gestion_terceros,id',
            'clasificacion_documental_trd_id' => 'nullable|exists:clasificacion_documental_trd,id',
            'config_divi_poli_id_afectado' => 'nullable|exists:config_divi_poli,id',
            'prioridad' => 'required|in:Normal,Urgente,Tutela',
            'fallo_judicial' => 'nullable|in:Si,No',
            'fechor_tramite' => 'nullable|date',
            'observaciones' => 'nullable|string|max:5000',
            'num_docu_afectado' => 'nullable|string|max:25',
            'nom_afectado' => 'nullable|string|max:100',
            'dir_afectado' => 'nullable|string|max:150',
            'tel_afectado' => 'nullable|string|max:50',
            'movil_afectado' => 'nullable|string|max:30',
            'detalle_solicitud' => 'required|string|max:5000',

            'modalidad' => 'nullable|string|max:100',
            'derecho_solicitado' => 'nullable|string|max:255',
            'area_afectada' => 'nullable|string|max:255',
            'funcionarios_implicados' => 'nullable|string|max:1000',
            'derecho_vulnerado' => 'nullable|string|max:255',
            'pretension' => 'nullable|string|max:2000',
            'area_mejora' => 'nullable|string|max:2000',
            'motivo_felicitacion' => 'nullable|string|max:2000',
            'autoridad_destino' => 'nullable|string|max:255',
            'tipo_persona' => 'nullable|in:Natural,Jurídica',
        ];

        if ($this->filled('ventanilla_radica_reci_id')) {
            $rules['ventanilla_radica_reci_id'] = 'nullable|exists:ventanilla_radica_reci,id|unique:ventanilla_pqrs,ventanilla_radica_reci_id';
        } else {
            $rules['nom_afectado'] = 'required|string|max:100';
            $rules['num_docu_afectado'] = 'required|string|max:25';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'ventanilla_radica_reci_id.exists' => 'El radicado seleccionado no existe.',
            'ventanilla_radica_reci_id.unique' => 'Este radicado ya tiene un PQRS asociado.',
            'tipo_pqrs_id.required' => 'El tipo de PQRS es obligatorio.',
            'tipo_pqrs_id.exists' => 'El tipo de PQRS seleccionado no es válido.',
            'gestion_tercero_id.exists' => 'El tercero seleccionado no es válido.',
            'clasificacion_documental_trd_id.exists' => 'La clasificación documental seleccionada no es válida.',
            'config_divi_poli_id_afectado.exists' => 'La división política del afectado no es válida.',
            'prioridad.required' => 'La prioridad es obligatoria.',
            'prioridad.in' => 'La prioridad debe ser Normal, Urgente o Tutela.',
            'fallo_judicial.in' => 'El campo fallo judicial debe ser Si o No.',
            'fechor_tramite.date' => 'La fecha y hora del trámite debe ser una fecha válida.',
            'observaciones.string' => 'Las observaciones deben ser un texto válido.',
            'observaciones.max' => 'Las observaciones no pueden superar los 5000 caracteres.',
            'nom_afectado.required' => 'El nombre del afectado es obligatorio.',
            'nom_afectado.string' => 'El nombre del afectado debe ser un texto válido.',
            'nom_afectado.max' => 'El nombre del afectado no puede superar los 100 caracteres.',
            'num_docu_afectado.required' => 'El número de documento del afectado es obligatorio.',
            'num_docu_afectado.string' => 'El número de documento debe ser un texto válido.',
            'num_docu_afectado.max' => 'El número de documento no puede superar los 25 caracteres.',
            'dir_afectado.string' => 'La dirección del afectado debe ser un texto válido.',
            'dir_afectado.max' => 'La dirección del afectado no puede superar los 150 caracteres.',
            'tel_afectado.string' => 'El teléfono del afectado debe ser un texto válido.',
            'tel_afectado.max' => 'El teléfono del afectado no puede superar los 50 caracteres.',
            'movil_afectado.string' => 'El móvil del afectado debe ser un texto válido.',
            'movil_afectado.max' => 'El móvil del afectado no puede superar los 30 caracteres.',
            'detalle_solicitud.required' => 'El detalle de la solicitud es obligatorio.',
            'detalle_solicitud.string' => 'El detalle de la solicitud debe ser un texto válido.',
            'detalle_solicitud.max' => 'El detalle no puede superar los 5000 caracteres.',
            'modalidad.string' => 'La modalidad debe ser un texto válido.',
            'modalidad.max' => 'La modalidad no puede exceder los 100 caracteres.',
            'derecho_solicitado.string' => 'El derecho solicitado debe ser un texto válido.',
            'derecho_solicitado.max' => 'El derecho solicitado no puede exceder los 255 caracteres.',
            'area_afectada.string' => 'El área afectada debe ser un texto válido.',
            'area_afectada.max' => 'El área afectada no puede exceder los 255 caracteres.',
            'funcionarios_implicados.string' => 'Los funcionarios implicados deben ser un texto válido.',
            'funcionarios_implicados.max' => 'La lista de funcionarios no puede superar los 1000 caracteres.',
            'derecho_vulnerado.string' => 'El derecho vulnerado debe ser un texto válido.',
            'derecho_vulnerado.max' => 'El derecho vulnerado no puede superar los 255 caracteres.',
            'pretension.string' => 'La pretensión debe ser un texto válido.',
            'pretension.max' => 'La pretensión no puede superar los 2000 caracteres.',
            'area_mejora.string' => 'El área de mejora debe ser un texto válido.',
            'area_mejora.max' => 'El área de mejora no puede superar los 2000 caracteres.',
            'motivo_felicitacion.string' => 'El motivo de felicitación debe ser un texto válido.',
            'motivo_felicitacion.max' => 'El motivo de felicitación no puede superar los 2000 caracteres.',
            'autoridad_destino.string' => 'La autoridad de destino debe ser un texto válido.',
            'autoridad_destino.max' => 'La autoridad de destino no puede exceder los 255 caracteres.',
            'tipo_persona.in' => 'El tipo de persona debe ser Natural o Jurídica.',
        ];
    }
}
