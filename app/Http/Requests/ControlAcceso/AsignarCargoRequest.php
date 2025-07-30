<?php

namespace App\Http\Requests\ControlAcceso;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarCargoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ajustar según los permisos del sistema
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
                'required',
                'integer',
                'exists:users,id'
            ],
            'organigrama_id' => [
                'required',
                'integer',
                'exists:calidad_organigrama,id',
                function ($attribute, $value, $fail) {
                    $cargo = CalidadOrganigrama::find($value);
                    if ($cargo && $cargo->tipo !== 'Cargo') {
                        $fail('El elemento seleccionado no es un cargo válido.');
                    }
                }
            ],
            'fecha_inicio' => [
                'nullable',
                'date',
                'after_or_equal:' . now()->subYears(5)->format('Y-m-d'),
                'before_or_equal:' . now()->addYears(1)->format('Y-m-d')
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:500'
            ],
            'finalizar_cargo_anterior' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'El ID del usuario es obligatorio.',
            'user_id.integer' => 'El ID del usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario especificado no existe.',

            'organigrama_id.required' => 'El ID del cargo es obligatorio.',
            'organigrama_id.integer' => 'El ID del cargo debe ser un número entero.',
            'organigrama_id.exists' => 'El cargo especificado no existe.',

            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a 5 años.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser posterior a 1 año.',

            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden tener más de 500 caracteres.',

            'finalizar_cargo_anterior.boolean' => 'El campo finalizar cargo anterior debe ser verdadero o falso.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Establecer fecha de inicio por defecto si no se proporciona
        if (!$this->has('fecha_inicio') || empty($this->fecha_inicio)) {
            $this->merge([
                'fecha_inicio' => now()->format('Y-m-d')
            ]);
        }

        // Establecer valor por defecto para finalizar cargo anterior
        if (!$this->has('finalizar_cargo_anterior')) {
            $this->merge([
                'finalizar_cargo_anterior' => true
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'organigrama_id' => 'cargo',
            'fecha_inicio' => 'fecha de inicio',
            'observaciones' => 'observaciones',
            'finalizar_cargo_anterior' => 'finalizar cargo anterior'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el usuario no tenga ya el mismo cargo activo
            if ($this->user_id && $this->organigrama_id) {
                $user = User::find($this->user_id);
                if ($user && $user->cargoActivo && $user->cargoActivo->organigrama_id == $this->organigrama_id) {
                    $validator->errors()->add('organigrama_id', 'El usuario ya tiene este cargo asignado actualmente.');
                }
            }

            // Validar que el cargo no esté ocupado por otro usuario (si aplica)
            if ($this->organigrama_id) {
                $cargo = CalidadOrganigrama::find($this->organigrama_id);
                if ($cargo && $cargo->tieneUsuariosAsignados()) {
                    $usuarioActivo = $cargo->getUsuarioActivo();
                    if ($usuarioActivo && $usuarioActivo->user_id != $this->user_id) {
                        $validator->errors()->add(
                            'organigrama_id',
                            'Este cargo ya está asignado a otro usuario: ' .
                                $usuarioActivo->user->nombres . ' ' . $usuarioActivo->user->apellidos
                        );
                    }
                }
            }
        });
    }
}
