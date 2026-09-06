<?php

namespace App\Http\Requests\ClasificacionDocumental;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTVDRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function tvdId(): int
    {
        return (int) $this->route('tvd');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dependenciaId = $this->input('dependencia_id')
            ?? ClasificacionDocumentalTVD::find($this->tvdId())?->dependencia_id;

        return [
            'tipo' => ['sometimes', 'required', 'string', 'in:SerieDocumental,SubSerieDocumental'],
            'cod' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('clasificacion_documental_tvd', 'cod')
                    ->where('dependencia_id', $dependenciaId)
                    ->ignore($this->tvdId()),
            ],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'soporte' => ['nullable', 'string', 'max:100'],
            'disposicion_final' => ['nullable', 'string', 'max:100'],
            'procedimiento' => ['nullable', 'string'],
            'gestion' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'central' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'total_anios' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'dependencia_id' => ['sometimes', 'required', 'integer', 'exists:calidad_organigrama,id'],
            'parent' => ['nullable', 'integer', 'exists:clasificacion_documental_tvd,id'],
            'estado' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $id = $this->tvdId();
            $tipo = $this->input('tipo') ?? ClasificacionDocumentalTVD::find($id)?->tipo;
            $parentId = $this->input('parent');

            if (! empty($parentId)) {
                if ((int) $parentId === $id) {
                    $validator->errors()->add('parent', 'Un elemento TVD no puede ser su propio padre.');

                    return;
                }

                if ($this->esDescendiente((int) $parentId, $id)) {
                    $validator->errors()->add('parent', 'No puede mover un elemento dentro de su propio subárbol.');

                    return;
                }
            }

            if ($tipo === 'SubSerieDocumental' && empty($parentId)) {
                $validator->errors()->add('parent', 'La SubSerie Documental requiere una Serie Documental padre.');

                return;
            }

            if ($tipo === 'SubSerieDocumental' && ! empty($parentId)) {
                $padre = ClasificacionDocumentalTVD::find((int) $parentId);

                if ($padre && $padre->tipo !== 'SerieDocumental') {
                    $validator->errors()->add('parent', 'El padre debe ser una Serie Documental.');
                }
            }

            if ($tipo === 'SerieDocumental' && ! empty($parentId)) {
                $validator->errors()->add('parent', 'La Serie Documental no puede tener padre.');
            }

            // Una Serie con subseries hijas no puede degradarse a SubSerie.
            if ($tipo === 'SubSerieDocumental' && ClasificacionDocumentalTVD::find($id)?->hasChildren()) {
                $validator->errors()->add('tipo', 'No puede cambiar el tipo: el elemento tiene hijos registrados.');
            }
        });
    }

    /**
     * Evita ciclos: $candidateParent no debe estar bajo $nodeId.
     */
    private function esDescendiente(int $candidateParent, int $nodeId): bool
    {
        $actual = ClasificacionDocumentalTVD::find($candidateParent);

        while ($actual) {
            if ((int) $actual->id === $nodeId) {
                return true;
            }

            $actual = $actual->parent ? ClasificacionDocumentalTVD::find($actual->parent) : null;
        }

        return false;
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
