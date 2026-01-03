<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfigSedeRequest extends FormRequest
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Remover campos que ya no existen en el modelo
        $this->request->remove('numeracion_unificada');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Obtener el ID de la sede desde la ruta
        $sede = $this->route('sede');
        $sedeId = is_object($sede) ? $sede->id : $sede;

        return [
            'nombre' => [
                'sometimes',
                'string',
                'max:100'
            ],
            'codigo' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('config_sedes', 'codigo')->ignore($sedeId, 'id')
            ],
            'direccion' => [
                'sometimes',
                'string',
                'max:255'
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20'
            ],
            'email' => [
                'nullable',
                'email',
                'max:100'
            ],
            'ubicacion' => [
                'nullable',
                'string',
                'max:255'
            ],
            'divi_poli_id' => [
                'nullable',
                'exists:config_divi_poli,id'
            ],
            'estado' => [
                'nullable',
                'in:0,1,true,false'
            ],

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
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede superar los 20 caracteres.',
            'codigo.unique' => 'El código de la sede ya está en uso.',
            'direccion.string' => 'La dirección debe ser una cadena de texto.',
            'direccion.max' => 'La dirección no puede superar los 255 caracteres.',
            'telefono.string' => 'El teléfono debe ser una cadena de texto.',
            'telefono.max' => 'El teléfono no puede superar los 20 caracteres.',
            'email.email' => 'El formato del email no es válido.',
            'email.max' => 'El email no puede superar los 100 caracteres.',
            'ubicacion.string' => 'La ubicación debe ser una cadena de texto.',
            'ubicacion.max' => 'La ubicación no puede superar los 255 caracteres.',
            'divi_poli_id.exists' => 'El departamento/policía seleccionada no existe.',
            'estado.in' => 'El estado debe ser 0, 1, true o false.',
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
            'nombre' => 'nombre de la sede',
            'codigo' => 'código de la sede',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'email',
            'ubicacion' => 'ubicación',
            'divi_poli_id' => 'departamento/policía',
            'estado' => 'estado'
        ];
    }
}
