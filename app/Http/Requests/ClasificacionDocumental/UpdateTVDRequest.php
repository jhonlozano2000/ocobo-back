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

    protected function actual(): ?ClasificacionDocumentalTVD
    {
        return ClasificacionDocumentalTVD::find($this->tvdId());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actual = $this->actual();
        $periodoId = $this->input('periodo_id') ?? $actual?->periodo_id;
        $unidadId = $this->input('unidad_id') ?? $actual?->unidad_id;

        return [
            'tipo' => ['sometimes', 'required', 'string', 'in:SerieDocumental,SubSerieDocumental'],
            'cod' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('clasificacion_documental_tvd', 'cod')
                    ->where('unidad_id', $unidadId)
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
            'periodo_id' => ['sometimes', 'required', 'integer', 'exists:fondo_acumulado_periodos,id'],
            // La unidad (nueva o la actual, si cambia el período) debe pertenecer al período
            'unidad_id' => [
                Rule::requiredIf($this->has('periodo_id')),
                'integer',
                Rule::exists('fondo_acumulado_unidades', 'id')->where('periodo_id', $periodoId),
            ],
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

            $actual = $this->actual();
            $unidadId = (int) ($this->input('unidad_id') ?? $actual?->unidad_id);

            if (! empty($parentId) && ($padre = ClasificacionDocumentalTVD::find((int) $parentId)) && (int) $padre->unidad_id !== $unidadId) {
                $validator->errors()->add('parent', 'El padre debe pertenecer a la misma unidad administrativa.');
            }

            // Una Serie con subseries hijas no puede degradarse a SubSerie ni cambiar de unidad sin ellas
            if ($actual?->hasChildren()) {
                if ($tipo === 'SubSerieDocumental') {
                    $validator->errors()->add('tipo', 'No puede cambiar el tipo: el elemento tiene hijos registrados.');
                }
                if ($unidadId !== (int) $actual->unidad_id) {
                    $validator->errors()->add('unidad_id', 'No puede cambiar la unidad: el elemento tiene subseries registradas.');
                }
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
            'cod.unique' => 'Ya existe un elemento TVD con este código en la unidad administrativa.',
            'periodo_id.exists' => 'El período seleccionado no existe.',
            'unidad_id.exists' => 'La unidad administrativa no pertenece al período seleccionado.',
            'parent.exists' => 'El elemento padre seleccionado no existe.',
        ];
    }
}
