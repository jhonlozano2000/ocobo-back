<?php

namespace App\Http\Requests\ClasificacionDocumental;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClasificacionDocumentalRequest extends FormRequest
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
        $trdId = $this->route('trd') ?? $this->route('id');
        $tipo = $this->input('tipo');

        $rules = [
            'tipo' => [
                'sometimes',
                'string',
                'in:Serie,SubSerie,TipoDocumento'
            ],
            'cod' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('clasificacion_documental_trd', 'cod')
                    ->where('dependencia_id', $this->input('dependencia_id'))
                    ->ignore($trdId)
            ],
            'nom' => [
                'sometimes',
                'string',
                'max:255'
            ],
            'dependencia_id' => [
                'sometimes',
                'integer',
                'exists:calidad_organigrama,id'
            ],
            'a_g' => [
                'nullable',
                'string',
                'max:10'
            ],
            'a_c' => [
                'nullable',
                'string',
                'max:10'
            ],
            'ct' => [
                'nullable',
                'boolean'
            ],
            'e' => [
                'nullable',
                'boolean'
            ],
            'm_d' => [
                'nullable',
                'boolean'
            ],
            's' => [
                'nullable',
                'boolean'
            ],
            'procedimiento' => [
                'nullable',
                'string',
                'max:500'
            ],
            'parent' => [
                'nullable',
                'integer',
                'exists:clasificacion_documental_trd,id',
                'different:' . $trdId // No puede ser padre de sí mismo
            ]
        ];

        // Validaciones específicas según el tipo
        if (in_array($tipo, ['SubSerie', 'TipoDocumento'])) {
            $rules['parent'] = [
                'required',
                'integer',
                'exists:clasificacion_documental_trd,id',
                'different:' . $trdId
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser Serie, SubSerie o TipoDocumento.',
            'cod.unique' => 'El código ya existe para esta dependencia.',
            'nom.max' => 'El nombre no puede superar los 255 caracteres.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
            'parent.required' => 'El elemento padre es obligatorio para SubSerie y TipoDocumento.',
            'parent.exists' => 'El elemento padre seleccionado no existe.',
            'parent.different' => 'Un elemento no puede ser padre de sí mismo.',
            'a_g.max' => 'Los años de gestión no pueden superar los 10 caracteres.',
            'a_c.max' => 'Los años de centralización no pueden superar los 10 caracteres.',
            'procedimiento.max' => 'El procedimiento no puede superar los 500 caracteres.',
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
            'tipo' => 'tipo de elemento',
            'cod' => 'código',
            'nom' => 'nombre',
            'dependencia_id' => 'dependencia',
            'a_g' => 'años de gestión',
            'a_c' => 'años de centralización',
            'ct' => 'conservación total',
            'e' => 'eliminación',
            'm_d' => 'microfilmación digital',
            's' => 'selección',
            'procedimiento' => 'procedimiento',
            'parent' => 'elemento padre',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores booleanos
        $this->merge([
            'ct' => $this->boolean('ct'),
            'e' => $this->boolean('e'),
            'm_d' => $this->boolean('m_d'),
            's' => $this->boolean('s'),
        ]);
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validarJerarquia($validator);
            $this->validarCambiosPermitidos($validator);
        });
    }

    /**
     * Valida la jerarquía según el tipo de elemento.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validarJerarquia($validator): void
    {
        $tipo = $this->input('tipo');
        $parentId = $this->input('parent');

        if (in_array($tipo, ['SubSerie', 'TipoDocumento']) && $parentId) {
            $parent = \App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::find($parentId);

            if (!$parent) {
                $validator->errors()->add('parent', 'El elemento padre seleccionado no existe.');
                return;
            }

            // Validar jerarquía para SubSerie
            if ($tipo === 'SubSerie' && $parent->tipo !== 'Serie') {
                $validator->errors()->add('parent', 'Las SubSeries solo pueden tener como padre una Serie.');
                return;
            }

            // Validar jerarquía para TipoDocumento
            if ($tipo === 'TipoDocumento' && !in_array($parent->tipo, ['Serie', 'SubSerie'])) {
                $validator->errors()->add('parent', 'Los Tipos de Documento solo pueden tener como padre una Serie o SubSerie.');
                return;
            }

            // Validar que el padre pertenezca a la misma dependencia
            $dependenciaId = $this->input('dependencia_id');
            if ($dependenciaId && $parent->dependencia_id != $dependenciaId) {
                $validator->errors()->add('parent', 'El elemento padre debe pertenecer a la misma dependencia.');
                return;
            }
        }
    }

    /**
     * Valida que los cambios sean permitidos según el estado actual.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validarCambiosPermitidos($validator): void
    {
        $trdId = $this->route('trd') ?? $this->route('id');
        $trd = \App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::find($trdId);

        if (!$trd) {
            $validator->errors()->add('id', 'El elemento TRD no existe.');
            return;
        }

        // Validar que no se cambie el tipo si tiene hijos
        $nuevoTipo = $this->input('tipo');
        if ($nuevoTipo && $nuevoTipo !== $trd->tipo && $trd->hasChildren()) {
            $validator->errors()->add('tipo', 'No se puede cambiar el tipo de un elemento que tiene hijos.');
            return;
        }

        // Validar que no se cambie la dependencia si tiene hijos
        $nuevaDependenciaId = $this->input('dependencia_id');
        if ($nuevaDependenciaId && $nuevaDependenciaId != $trd->dependencia_id && $trd->hasChildren()) {
            $validator->errors()->add('dependencia_id', 'No se puede cambiar la dependencia de un elemento que tiene hijos.');
            return;
        }
    }
}
