<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VentanillaRadicaEnviadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('radica_enviada') ?? $this->route('id');
        $uniqueRule = Rule::unique('ventanilla_radica_enviados', 'num_radicado');
        if ($id) {
            $uniqueRule->ignore($id);
        }

        return [
            'clasifica_documen_id' => 'required|exists:clasificacion_documental_trd,id',
            'tercero_enviado_id' => 'required|exists:gestion_terceros,id',
            'medio_enviado_id' => 'required|exists:config_listas_detalles,id',
            'tipo_respuesta_id' => 'required|exists:config_listas_detalles,id',
            'config_server_id' => 'nullable|exists:config_server_archivos,id',
            'usuario_crea' => 'required|exists:users,id',
            'subido_por' => 'nullable|exists:users,id',
            'fec_docu' => 'nullable|date',
            'num_folios' => 'required|integer|min:0',
            'num_anexos' => 'required|integer|min:0',
            'descrip_anexos' => 'required|string|max:300',
            'asunto' => 'required|string|max:300',
            'radicado_respuesta' => 'nullable|string|max:300',
            'num_radicado' => ['nullable', 'string', 'max:50', $uniqueRule],
        ];
    }

    public function messages(): array
    {
        return [
            'clasifica_documen_id.required' => 'La clasificación documental es obligatoria.',
            'clasifica_documen_id.exists' => 'La clasificación documental no es válida.',
            'tercero_enviado_id.required' => 'El destinatario es obligatorio.',
            'tercero_enviado_id.exists' => 'El destinatario no es válido.',
            'medio_enviado_id.required' => 'El medio de envío es obligatorio.',
            'medio_enviado_id.exists' => 'El medio de envío no es válido.',
            'tipo_respuesta_id.required' => 'El tipo de respuesta es obligatorio.',
            'tipo_respuesta_id.exists' => 'El tipo de respuesta no es válido.',
            'config_server_id.exists' => 'El servidor de archivos no es válido.',
            'usuario_crea.required' => 'El usuario creador es obligatorio.',
            'usuario_crea.exists' => 'El usuario creador no es válido.',
            'subido_por.exists' => 'El usuario que subió el archivo no es válido.',
            'fec_docu.date' => 'La fecha del documento debe ser una fecha válida.',
            'num_folios.required' => 'El número de folios es obligatorio.',
            'num_folios.integer' => 'El número de folios debe ser un número entero.',
            'num_anexos.required' => 'El número de anexos es obligatorio.',
            'num_anexos.integer' => 'El número de anexos debe ser un número entero.',
            'descrip_anexos.required' => 'La descripción de anexos es obligatoria.',
            'descrip_anexos.max' => 'La descripción de anexos no puede superar los 300 caracteres.',
            'asunto.required' => 'El asunto es obligatorio.',
            'asunto.max' => 'El asunto no puede superar los 300 caracteres.',
            'radicado_respuesta.max' => 'El radicado de respuesta no puede superar los 300 caracteres.',
            'num_radicado.unique' => 'El número de radicado ya existe.',
        ];
    }
}
