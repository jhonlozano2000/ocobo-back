<?php

namespace App\Http\Requests\Ventanilla\Recibidos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRadicadoReciboRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * OWASP A01:2021 - Broken Access Control
     * ISO 27001 A.9.4.2 - Rights management
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Verificar permiso específico para crear radicados recibidos
        // Según el patrón de permisos: "Radicar -> Cores. Recibida -> Crear"
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Crear');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'clasifica_documen_id' => 'required|exists:clasificacion_documental_trd,id',
            'tercero_id' => 'required|exists:gestion_terceros,id',
            'medio_recep_id' => 'required|exists:config_listas_detalles,id',
            'config_server_id' => 'nullable|exists:config_server_archivos,id',
            'usuario_crea' => 'nullable|exists:users,id',
            'uploaded_by' => 'nullable|exists:users,id',
            'fec_docu' => 'nullable|date',
            'fec_venci' => 'nullable|date',
            'fec_radicado' => 'nullable|date',
            'dias_vencimiento' => 'nullable|integer|min:0',
            'num_folios' => 'required|integer|min:0',
            'num_anexos' => 'required|integer|min:0',
            'descrip_anexos' => 'nullable|string|max:300',
            'asunto' => 'nullable|string|max:300',
            'tipo_solicitud_id' => 'nullable|exists:config_listas_detalles,id',
            'observaciones' => 'nullable|string|max:1000',
            'num_radicado' => ['nullable', 'string', 'max:50', Rule::unique('ventanilla_radica_reci', 'num_radicado')],
            'crear_pqrs' => 'nullable|boolean',
            'tipo_pqrs_id' => 'nullable|required_if:crear_pqrs,true|exists:config_listas_detalles,id',
            'prioridad' => 'nullable|required_if:crear_pqrs,true|in:Normal,Urgente,Tutela',
            'fallo_judicial' => 'nullable|in:Si,No',
            'observaciones_pqrs' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'clasifica_documen_id.required' => 'La clasificación documental es obligatoria según TRD.',
            'clasifica_documen_id.exists' => 'La clasificación documental no es válida.',
            'tercero_id.required' => 'El tercero es obligatorio.',
            'tercero_id.exists' => 'El tercero no es válido.',
            'medio_recep_id.required' => 'El medio de recepción es obligatorio.',
            'medio_recep_id.exists' => 'El medio de recepción no es válido.',
            'config_server_id.exists' => 'El servidor de archivos no es válido.',
            'usuario_crea.exists' => 'El usuario que creó el radicado no es válido.',
            'uploaded_by.exists' => 'El usuario que subió el archivo no es válido.',
            'fec_venci.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'fec_radicado.date' => 'La fecha de radicado debe ser una fecha válida.',
            'num_folios.required' => 'El número de folios es obligatorio.',
            'num_folios.integer' => 'El número de folios debe ser un número entero.',
            'num_anexos.required' => 'El número de anexos es obligatorio.',
            'num_anexos.integer' => 'El número de anexos debe ser un número entero.',
            'descrip_anexos.max' => 'La descripción de anexos no puede superar los 300 caracteres.',
            'asunto.max' => 'El asunto no puede superar los 300 caracteres.',
            'num_radicado.unique' => 'El número de radicado ya existe, por favor intente nuevamente.',
            'tipo_solicitud_id.exists' => 'El tipo de solicitud seleccionado no es válido.',
            'observaciones.max' => 'Las observaciones no pueden superar los 1000 caracteres.',
            'tipo_pqrs_id.required_if' => 'El tipo de PQRS es obligatorio cuando se solicita crear una PQRS.',
            'tipo_pqrs_id.exists' => 'El tipo de PQRS seleccionado no es válido.',
            'prioridad.required_if' => 'La prioridad es obligatoria cuando se solicita crear una PQRS.',
            'prioridad.in' => 'La prioridad debe ser Normal, Urgente o Tutela.',
            'fallo_judicial.in' => 'El campo fallo judicial debe ser Si o No.',
        ];
    }
}
