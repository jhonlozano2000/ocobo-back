<?php

namespace App\Http\Requests\Ventanilla;

use Illuminate\Foundation\Http\FormRequest;

class ListRadicadosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // La autorización se maneja a través de middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100'
            ],
            'fecha_desde' => [
                'nullable',
                'date',
                'before_or_equal:fecha_hasta'
            ],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde'
            ],
            'clasifica_documen_id' => [
                'nullable',
                'integer',
                'exists:clasificacion_documental_trd,id'
            ],
            'tercero_id' => [
                'nullable',
                'integer',
                'exists:gestion_terceros,id'
            ],
            'estado' => [
                'nullable',
                'in:0,1'
            ],
            'usuario_responsable' => [
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'search.string' => 'El término de búsqueda debe ser una cadena de texto.',
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'fecha_desde.date' => 'La fecha desde debe tener un formato válido.',
            'fecha_desde.before_or_equal' => 'La fecha desde debe ser anterior o igual a la fecha hasta.',
            'fecha_hasta.date' => 'La fecha hasta debe tener un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha desde.',
            'clasifica_documen_id.integer' => 'El ID de clasificación documental debe ser un número entero.',
            'clasifica_documen_id.exists' => 'La clasificación documental seleccionada no existe.',
            'tercero_id.integer' => 'El ID del tercero debe ser un número entero.',
            'tercero_id.exists' => 'El tercero seleccionado no existe.',
            'estado.in' => 'El estado debe ser 0 o 1.',
            'usuario_responsable.integer' => 'El ID del usuario responsable debe ser un número entero.',
            'usuario_responsable.exists' => 'El usuario responsable seleccionado no existe.',
            'per_page.integer' => 'El número de elementos por página debe ser un número entero.',
            'per_page.min' => 'El número de elementos por página debe ser al menos 1.',
            'per_page.max' => 'El número de elementos por página no puede superar 100.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'search' => 'término de búsqueda',
            'fecha_desde' => 'fecha desde',
            'fecha_hasta' => 'fecha hasta',
            'clasifica_documen_id' => 'clasificación documental',
            'tercero_id' => 'tercero',
            'estado' => 'estado',
            'usuario_responsable' => 'usuario responsable',
            'per_page' => 'elementos por página'
        ];
    }
}
