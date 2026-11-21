<?php

namespace App\Http\Requests\Ia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProyectarBorradorIaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la proyección de borrador con IA.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_radicado' => [
                'nullable',
                'string',
                'max:50',
            ],
            'asunto' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
            'contenido' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'tipo_solicitud' => [
                'nullable',
                'string',
                'max:100',
            ],
            'flag_hc' => [
                'nullable',
                'boolean',
            ],
            'flag_tutela' => [
                'nullable',
                'boolean',
            ],
            'top_k' => [
                'nullable',
                'integer',
                'min:1',
                'max:10',
            ],
            'instrucciones_adicionales' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'anonimizar_automaticamente' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'asunto.required' => 'El asunto del radicado es obligatorio para proyectar el borrador.',
            'asunto.string' => 'El asunto debe ser una cadena de texto.',
            'asunto.min' => 'El asunto debe contener al menos 3 caracteres.',
            'asunto.max' => 'El asunto no puede exceder los 1000 caracteres.',
            'contenido.string' => 'El contenido o cuerpo del radicado debe ser una cadena de texto.',
            'contenido.max' => 'El contenido no puede superar los 10.000 caracteres.',
            'tipo_solicitud.string' => 'El tipo de solicitud debe ser una cadena de texto.',
            'top_k.integer' => 'El parámetro top_k debe ser un número entero.',
            'top_k.min' => 'El parámetro top_k debe ser al menos 1.',
            'top_k.max' => 'El parámetro top_k no puede superar 10 fragmentos.',
            'instrucciones_adicionales.max' => 'Las instrucciones adicionales no pueden exceder 2000 caracteres.',
        ];
    }

    /**
     * Atributos personalizados para los campos de validación.
     */
    public function attributes(): array
    {
        return [
            'id_radicado' => 'número de radicado',
            'asunto' => 'asunto del radicado',
            'contenido' => 'cuerpo o petición del ciudadano',
            'tipo_solicitud' => 'tipo de solicitud o trámite',
            'flag_hc' => 'indicador de historia clínica',
            'flag_tutela' => 'indicador de tutela',
            'top_k' => 'cantidad de fragmentos precedentes',
            'instrucciones_adicionales' => 'instrucciones particulares del funcionario',
            'anonimizar_automaticamente' => 'anonimización automática',
        ];
    }
}
