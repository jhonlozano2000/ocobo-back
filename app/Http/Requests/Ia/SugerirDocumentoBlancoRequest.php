<?php

namespace App\Http\Requests\Ia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de solicitud para sugerir borrador inicial en un documento en blanco.
 *
 * Exige obligatoriamente una frase breve (10–240 caracteres) y una de las 4 tipologías
 * documentales institucionales de OCOBO (memorando, acta, circular u oficio) para evitar
 * ejecuciones innecesarias del LLM local y prevenir alucinaciones.
 */
class SugerirDocumentoBlancoRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado está autorizado a usar esta funcionalidad.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza la frase y la tipología documental antes de aplicar las reglas de validación.
     */
    protected function prepareForValidation(): void
    {
        $datos = [];

        if ($this->has('frase') && is_string($this->input('frase'))) {
            $datos['frase'] = preg_replace('/\s+/', ' ', trim($this->input('frase')));
        }

        if ($this->has('tipo_documento') && is_string($this->input('tipo_documento'))) {
            $datos['tipo_documento'] = strtolower(trim($this->input('tipo_documento')));
        }

        if (! empty($datos)) {
            $this->merge($datos);
        }
    }

    /**
     * Reglas de validación para la sugerencia de borrador en documento en blanco.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'frase' => [
                'required',
                'string',
                'min:10',
                'max:240',
            ],
            'tipo_documento' => [
                'required',
                'string',
                'in:memorando,acta,circular,oficio',
            ],
            'serie_trd' => [
                'nullable',
                'string',
                'max:150',
            ],
            'subserie_trd' => [
                'nullable',
                'string',
                'max:150',
            ],
            'top_k' => [
                'nullable',
                'integer',
                'min:1',
                'max:3',
            ],
            'documento_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'modelo_solicitado' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * Mensajes de validación en español orientados al funcionario.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'frase.required' => 'Debe ingresar una frase breve que indique el propósito o tema del documento.',
            'frase.string' => 'La frase de propósito debe ser un texto válido.',
            'frase.min' => 'La frase debe tener al menos :min caracteres para generar una sugerencia precisa.',
            'frase.max' => 'La frase no puede superar los :max caracteres.',
            'tipo_documento.required' => 'Debe seleccionar una tipología documental (memorando, acta, circular u oficio).',
            'tipo_documento.in' => 'La tipología documental seleccionada no es válida. Valores permitidos: memorando, acta, circular u oficio.',
            'top_k.integer' => 'La cantidad de precedentes debe ser un número entero.',
            'top_k.min' => 'Debe consultar al menos :min precedente.',
            'top_k.max' => 'Para documentos en blanco se permiten máximo :max precedentes de estilo.',
        ];
    }

    /**
     * Nombres amigables de los atributos para mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'frase' => 'frase de propósito',
            'tipo_documento' => 'tipología documental',
            'serie_trd' => 'serie TRD',
            'subserie_trd' => 'subserie TRD',
            'top_k' => 'cantidad de precedentes',
            'documento_id' => 'documento colaborativo',
            'modelo_solicitado' => 'modelo de IA',
        ];
    }
}
