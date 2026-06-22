<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para radicar un correo electrónico como recibido o enviado.
 */
class RadicarEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_radicado' => 'required|in:recibido,enviado',
            'clasifica_documen_id' => 'required|exists:clasificacion_documental_trd,id',
            'tercero_id' => 'nullable|exists:gestion_terceros,id',
            'nom_razo_soci' => 'nullable|string|max:200',
            'num_docu_nit' => 'nullable|string|max:20',
            'asunto' => 'nullable|string|max:300',
            'notas' => 'nullable|string|max:1000',
            'num_folios' => 'nullable|integer|min:0',
            'num_anexos' => 'nullable|integer|min:0',
            'descrip_anexos' => 'nullable|string|max:300',
            'medio_recep_id' => 'required_if:tipo_radicado,recibido|nullable|exists:config_listas_detalles,id',
            'medio_enviado_id' => 'required_if:tipo_radicado,enviado|nullable|exists:config_listas_detalles,id',
            'tipo_solicitud_id' => 'required_if:tipo_radicado,recibido|nullable|exists:config_listas_detalles,id',
            'tipo_respuesta_id' => 'nullable|exists:config_listas_detalles,id',
            'responsable_id' => 'nullable|exists:users,id',
            'fec_documento' => 'required|date|before_or_equal:today',
            'fec_venci' => 'nullable|date|after_or_equal:fec_documento',
            'prioridad' => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'tipo_pqrs_id' => 'nullable|exists:config_listas_detalles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_radicado.required' => 'Debe seleccionar el tipo de radicado (Recibido o Enviado).',
            'tipo_radicado.in' => 'El tipo de radicado debe ser recibido o enviado.',
            'clasifica_documen_id.required' => 'La clasificación documental es obligatoria.',
            'clasifica_documen_id.exists' => 'La clasificación documental seleccionada no es válida.',
            'tercero_id.exists' => 'El remitente seleccionado no es válido.',
            'nom_razo_soci.max' => 'El nombre o razón social no puede superar los 200 caracteres.',
            'num_docu_nit.max' => 'El número de documento no puede superar los 20 caracteres.',
            'asunto.max' => 'El asunto no puede superar los 300 caracteres.',
            'notas.max' => 'Las notas no pueden superar los 1000 caracteres.',
            'num_folios.integer' => 'El número de folios debe ser un número entero.',
            'num_folios.min' => 'El número de folios no puede ser negativo.',
            'num_anexos.integer' => 'El número de anexos debe ser un número entero.',
            'num_anexos.min' => 'El número de anexos no puede ser negativo.',
            'descrip_anexos.max' => 'La descripción de anexos no puede superar los 300 caracteres.',
            'medio_recep_id.required' => 'El medio de recepción es obligatorio para radicados recibidos.',
            'medio_recep_id.exists' => 'El medio de recepción seleccionado no es válido.',
            'medio_enviado_id.required' => 'El medio de envío es obligatorio para radicados enviados.',
            'medio_enviado_id.exists' => 'El medio de envío seleccionado no es válido.',
            'tipo_solicitud_id.required_if' => 'El tipo de solicitud es obligatorio para radicados recibidos.',
            'tipo_solicitud_id.exists' => 'El tipo de solicitud seleccionado no es válido.',
            'tipo_respuesta_id.exists' => 'El tipo de respuesta seleccionado no es válido.',
            'responsable_id.exists' => 'El responsable seleccionado no es válido.',
            'fec_documento.required' => 'La fecha del documento es obligatoria.',
            'fec_documento.date' => 'La fecha del documento no es válida.',
            'fec_documento.before_or_equal' => 'La fecha del documento no puede ser futura.',
            'fec_venci.date' => 'La fecha de vencimiento no es válida.',
            'fec_venci.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a la fecha del documento.',
            'prioridad.in' => 'La prioridad debe ser ALTA, MEDIA o BAJA.',
            'tipo_pqrs_id.exists' => 'El tipo de PQRS seleccionado no es válido.',
        ];
    }
}
