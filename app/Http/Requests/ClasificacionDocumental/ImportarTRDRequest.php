<?php

namespace App\Http\Requests\ClasificacionDocumental;

use Illuminate\Foundation\Http\FormRequest;

class ImportarTRDRequest extends FormRequest
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
            'file' => 'required|file|mimes:xlsx|max:2048',
            'dependencia_id' => 'required|exists:calidad_organigrama,id'
        ];
    }

    /**
     * Mensajes personalizados para las reglas de validación.
     */
    public function messages()
    {
        return [
            'file.required' => 'Debe adjuntar un archivo para importar la TRD.',
            'file.file' => 'El archivo debe ser un archivo válido.',
            'file.mimes' => 'El archivo debe estar en formato Excel (.xlsx).',
            'file.max' => 'El tamaño del archivo no debe superar los 2MB.',
            'dependencia_id.required' => 'Debe seleccionar una dependencia para importar la TRD.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe en el sistema.'
        ];
    }
}
