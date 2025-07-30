<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class ListUserCargosRequest extends FormRequest
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
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'cargo_id' => [
                'nullable',
                'integer',
                'exists:calidad_organigrama,id'
            ],
            'estado' => [
                'nullable',
                'boolean'
            ],
            'fecha_desde' => [
                'nullable',
                'date'
            ],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde'
            ],
            'incluir_finalizados' => [
                'nullable',
                'boolean'
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ],
            'sort_by' => [
                'nullable',
                'string',
                'in:fecha_inicio,fecha_fin,created_at'
            ],
            'sort_order' => [
                'nullable',
                'string',
                'in:asc,desc'
            ]
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'user_id.integer' => 'El ID del usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario especificado no existe.',

            'cargo_id.integer' => 'El ID del cargo debe ser un número entero.',
            'cargo_id.exists' => 'El cargo especificado no existe.',

            'estado.boolean' => 'El estado debe ser verdadero o falso.',

            'fecha_desde.date' => 'La fecha desde debe ser una fecha válida.',
            'fecha_hasta.date' => 'La fecha hasta debe ser una fecha válida.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha desde.',

            'incluir_finalizados.boolean' => 'Incluir finalizados debe ser verdadero o falso.',

            'per_page.integer' => 'El número de elementos por página debe ser un entero.',
            'per_page.min' => 'El número de elementos por página debe ser al menos 1.',
            'per_page.max' => 'El número de elementos por página no puede ser mayor a 100.',

            'sort_by.in' => 'El campo de ordenamiento debe ser: fecha_inicio, fecha_fin o created_at.',
            'sort_order.in' => 'El orden debe ser: asc o desc.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Establecer valores por defecto
        $defaults = [
            'incluir_finalizados' => false,
            'per_page' => 15,
            'sort_by' => 'fecha_inicio',
            'sort_order' => 'desc'
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key) || $this->get($key) === null) {
                $this->merge([$key => $value]);
            }
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'cargo_id' => 'cargo',
            'estado' => 'estado',
            'fecha_desde' => 'fecha desde',
            'fecha_hasta' => 'fecha hasta',
            'incluir_finalizados' => 'incluir finalizados',
            'per_page' => 'elementos por página',
            'sort_by' => 'ordenar por',
            'sort_order' => 'orden'
        ];
    }
}
