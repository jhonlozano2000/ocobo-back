<?php

namespace App\Http\Requests\ClasificacionDocumental;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTVDRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'in:SerieDocumental,SubSerieDocumental'],
            'cod' => [
                'required',
                'string',
                'max:20',
                Rule::unique('clasificacion_documental_tvd', 'cod')
                    ->where('dependencia_id', $this->input('dependencia_id')),
            ],
            'nom' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'soporte' => ['nullable', 'string', 'max:100'],
            'disposicion_final' => ['nullable', 'string', 'max:100'],
            'procedimiento' => ['nullable', 'string'],
            'gestion' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'central' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'total_anios' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'dependencia_id' => ['required', 'integer', 'exists:calidad_organigrama,id'],
            'parent' => ['nullable', 'integer', 'exists:clasificacion_documental_tvd,id'],
            'estado' => ['nullable', 'boolean'],
        ];
    }

    /**
     * La jerarquía TVD solo tiene dos niveles: SerieDocumental (raíz) y
     * SubSerieDocumental (hija de una Serie de la misma dependencia).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tipo = $this->input('tipo');
            $parentId = $this->input('parent');

            if ($tipo === 'SubSerieDocumental') {
                if (empty($parentId)) {
                    $validator->errors()->add('parent', 'La SubSerie Documental requiere una Serie Documental padre.');

                    return;
                }

                $this->validarPadre($validator, (int) $parentId);
            }

            if ($tipo === 'SerieDocumental' && ! empty($parentId)) {
                $validator->errors()->add('parent', 'La Serie Documental no puede tener padre.');
            }
        });
    }

    protected function validarPadre(Validator $validator, int $parentId): void
    {
        $padre = ClasificacionDocumentalTVD::find($parentId);

        if (! $padre) {
            return;
        }

        if ($padre->tipo !== 'SerieDocumental') {
            $validator->errors()->add('parent', 'El padre debe ser una Serie Documental.');
        }

        if ((int) $padre->dependencia_id !== (int) $this->input('dependencia_id')) {
            $validator->errors()->add('parent', 'El padre debe pertenecer a la misma dependencia.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser SerieDocumental o SubSerieDocumental.',
            'cod.unique' => 'Ya existe un elemento TVD con este código en la dependencia.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
            'parent.exists' => 'El elemento padre seleccionado no existe.',
        ];
    }
}
