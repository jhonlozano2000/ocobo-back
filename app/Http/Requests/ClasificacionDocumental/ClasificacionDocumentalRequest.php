<?php

namespace App\Http\Requests\ClasificacionDocumental;

use Illuminate\Foundation\Http\FormRequest;

class ClasificacionDocumentalRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx|max:2048',
            'dependencia_id' => 'required|exists:calidad_organigrama,id'
        ];
    }

    /**
     * Define los mensajes de error personalizados para cada validación.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Debe adjuntar un archivo de TRD.',
            'file.file' => 'El archivo debe ser válido.',
            'file.mimes' => 'El archivo debe estar en formato Excel (.xlsx).',
            'file.max' => 'El tamaño del archivo no debe superar los 2MB.',
            'dependencia_id.required' => 'Debe seleccionar una dependencia válida.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
        ];
    }
}
