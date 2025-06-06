<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VentanillaRadicaReciRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'clasifica_documen_id' => 'required|exists:clasificacion_documental_trd,id',
            'tercero_id' => 'required|exists:gestion_terceros,id',
            'medio_recep_id' => 'required|exists:config_listas_detalles,id',
            'config_server_id' => 'nullable|exists:config_server_archivos,id',
            'fec_venci' => 'nullable|date',
            'num_folios' => 'required|integer|min:0',
            'num_anexos' => 'required|integer|min:0',
            'descrip_anexos' => 'nullable|string|max:300',
            'asunto' => 'nullable|string|max:300',
            'num_radicado' => ['nullable', 'string', 'max:50', Rule::unique('ventanilla_radica_reci', 'num_radicado')],
        ];
    }

    public function messages()
    {
        return [
            'clasifica_documen_id.required' => 'La clasificación documental es obligatoria.',
            'clasifica_documen_id.exists' => 'La clasificación documental no es válida.',
            'tercero_id.required' => 'El tercero es obligatorio.',
            'tercero_id.exists' => 'El tercero no es válido.',
            'medio_recep_id.required' => 'El medio de recepción es obligatorio.',
            'medio_recep_id.exists' => 'El medio de recepción no es válido.',
            'num_folios.required' => 'El número de folios es obligatorio.',
            'num_folios.integer' => 'El número de folios debe ser un número entero.',
            'num_anexos.required' => 'El número de anexos es obligatorio.',
            'num_anexos.integer' => 'El número de anexos debe ser un número entero.',
            'descrip_anexos.max' => 'La descripción de anexos no puede superar los 300 caracteres.',
            'asunto.max' => 'El asunto no puede superar los 300 caracteres.',
            'num_radicado.unique' => 'El número de radicado ya existe, por favor intente nuevamente.',
        ];
    }
}
