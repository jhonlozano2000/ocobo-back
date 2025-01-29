<?php

namespace App\Http\Requests\Calidad;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Foundation\Http\FormRequest;

class CalidadOrganigramaRequest extends FormRequest
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
        $id = $this->route('id'); // Captura el ID en caso de actualización
        return [
            'tipo' => 'required|in:Dependencia,Oficina,Cargo',
            'nom_organico' => 'required|min:2|max:100',
            'cod_organico' => 'nullable|string|max:10|unique:calidad_organigrama,cod_organico,' . $id,
            'observaciones' => 'nullable|string',
            'parent' => [
                'nullable',
                'exists:calidad_organigrama,id',
                function ($attribute, $value, $fail) {
                    $parentNode = CalidadOrganigrama::find($value);

                    if ($parentNode) {
                        // Un Cargo NO puede tener hijos
                        if ($parentNode->tipo === 'Cargo') {
                            $fail('No se pueden agregar elementos a un Cargo.');
                        }

                        // Una Oficina solo puede pertenecer a una Dependencia
                        if ($this->tipo === 'Oficina' && $parentNode->tipo !== 'Dependencia') {
                            $fail('Las Oficinas solo pueden pertenecer a una Dependencia.');
                        }

                        // Una Dependencia NO puede estar dentro de una Oficina
                        if ($this->tipo === 'Dependencia' && $parentNode->tipo === 'Oficina') {
                            $fail('No se pueden agregar Dependencias dentro de una Oficina.');
                        }
                    }
                }
            ],
        ];
    }

    /**
     * Define los mensajes de error personalizados.
     */
    public function messages()
    {
        return [
            'tipo.required' => 'El tipo de organismo es obligatorio.',
            'tipo.in' => 'El tipo debe ser Dependencia, Oficina o Cargo.',
            'nom_organico.required' => 'El nombre del organismo es obligatorio.',
            'nom_organico.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nom_organico.max' => 'El nombre no puede superar los 100 caracteres.',
            'cod_organico.max' => 'El código orgánico no puede superar los 10 caracteres.',
            'cod_organico.unique' => 'El código orgánico ya está en uso.',
            'parent.exists' => 'El nodo padre seleccionado no existe.',
        ];
    }
}
