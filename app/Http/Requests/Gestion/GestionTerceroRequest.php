<?php

namespace App\Http\Requests\Gestion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionTerceroRequest extends FormRequest
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
        $terceroId = $this->route('gestion_tercero'); // Capturar correctamente el ID de la URL

        return [
            'municipio_id' => 'nullable|exists:config_divi_poli,id',
            'num_docu_nit' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique('gestion_terceros', 'num_docu_nit')->ignore($terceroId, 'id'),
            ],
            'nom_razo_soci' => 'required|string|max:150',
            'direccion' => 'nullable|string|max:150',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:70',
            'tipo' => ['required', Rule::in(['Natural', 'Juridico'])],
            'notifica_email' => 'boolean',
            'notifica_msm' => 'boolean',
        ];
    }

    public function messages()
    {
        return [
            'municipio_id.exists' => 'El municipio seleccionado no es válido.',

            'num_docu_nit.unique' => 'El número de documento o NIT ya está registrado.',
            'num_docu_nit.string' => 'El número de documento o NIT debe ser una cadena de texto.',
            'num_docu_nit.max' => 'El número de documento o NIT no puede superar los 25 caracteres.',

            'nom_razo_soci.required' => 'El nombre o razón social es obligatorio.',
            'nom_razo_soci.string' => 'El nombre o razón social debe ser una cadena de texto.',
            'nom_razo_soci.max' => 'El nombre o razón social no puede superar los 150 caracteres.',

            'direccion.string' => 'La dirección debe ser una cadena de texto.',
            'direccion.max' => 'La dirección no puede superar los 150 caracteres.',

            'telefono.string' => 'El teléfono debe ser una cadena de texto.',
            'telefono.max' => 'El teléfono no puede superar los 30 caracteres.',

            'email.email' => 'Debe ingresar un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar los 70 caracteres.',

            'tipo.required' => 'El tipo de tercero es obligatorio.',
            'tipo.in' => 'El tipo de tercero debe ser "Natural" o "Juridico".',

            'notifica_email.boolean' => 'El campo de notificación por email debe ser verdadero o falso.',
            'notifica_msm.boolean' => 'El campo de notificación por SMS debe ser verdadero o falso.',
        ];
    }
}
