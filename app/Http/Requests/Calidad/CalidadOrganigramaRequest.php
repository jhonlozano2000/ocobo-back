<?php

namespace App\Http\Requests\Calidad;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalidadOrganigramaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
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
        // Obtener el ID del nodo desde la ruta (puede venir como 'organigrama' o 'calidadOrganigrama')
        $organigrama = $this->route('organigrama') ?? $this->route('calidadOrganigrama');
        $organigramaId = null;
        
        if ($organigrama) {
            $organigramaId = is_object($organigrama) ? $organigrama->id : $organigrama;
        }

        $codOrganicoRules = [
            'nullable',
            'string',
            'max:10',
        ];

        // Solo aplicar unique si hay un ID (update), y solo si se proporciona cod_organico
        if ($organigramaId !== null) {
            $codOrganicoRules[] = Rule::unique('calidad_organigrama', 'cod_organico')->ignore($organigramaId, 'id');
        } else {
            $codOrganicoRules[] = Rule::unique('calidad_organigrama', 'cod_organico');
        }

        return [
            'tipo' => [
                'required',
                'string',
                'in:Dependencia,Oficina,Cargo'
            ],
            'nom_organico' => [
                'required',
                'string',
                'min:2',
                'max:100'
            ],
            'cod_organico' => $codOrganicoRules,
            'observaciones' => [
                'nullable',
                'string',
                'max:500'
            ],
            'parent' => [
                'nullable',
                'integer',
                'exists:calidad_organigrama,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
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

                            // Un Cargo solo puede pertenecer a una Oficina o Dependencia
                            if ($this->tipo === 'Cargo' && !in_array($parentNode->tipo, ['Dependencia', 'Oficina'])) {
                                $fail('Los Cargos solo pueden pertenecer a una Dependencia u Oficina.');
                            }
                        }
                    }
                }
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
            'tipo.required' => 'El tipo de organismo es obligatorio.',
            'tipo.in' => 'El tipo debe ser Dependencia, Oficina o Cargo.',
            'nom_organico.required' => 'El nombre del organismo es obligatorio.',
            'nom_organico.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nom_organico.max' => 'El nombre no puede superar los 100 caracteres.',
            'cod_organico.max' => 'El código orgánico no puede superar los 10 caracteres.',
            'cod_organico.unique' => 'El código orgánico ya está en uso.',
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
            'parent.integer' => 'El ID del nodo padre debe ser un número entero.',
            'parent.exists' => 'El nodo padre seleccionado no existe.'
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
            'tipo' => 'tipo de organismo',
            'nom_organico' => 'nombre del organismo',
            'cod_organico' => 'código orgánico',
            'observaciones' => 'observaciones',
            'parent' => 'nodo padre'
        ];
    }
}
