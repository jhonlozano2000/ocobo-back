<?php

namespace App\Http\Requests\ClasificacionDocumental;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use Illuminate\Foundation\Http\FormRequest;

class StoreClasificacionDocumentalRequest extends FormRequest
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
        return [
            'tipo' => 'required|in:Serie,SubSerie,TipoDocumento',
            'nom' => 'required|string|max:100',
            'cod' => 'nullable|string|max:10|unique:clasificacion_documental_trd,cod',
            'a_g' => 'nullable|string|max:5',
            'a_c' => 'nullable|string|max:5',
            'ct' => 'nullable|boolean',
            'e' => 'nullable|boolean',
            'm_d' => 'nullable|boolean',
            's' => 'nullable|boolean',
            'procedimiento' => 'nullable|string',
            'parent' => [
                function ($attribute, $value, $fail) {
                    $tipo = $this->input('tipo');

                    // Si es SubSerie o TipoDocumento, el parent es obligatorio
                    if (in_array($tipo, ['SubSerie', 'TipoDocumento']) && is_null($value)) {
                        $fail('El campo parent es obligatorio para SubSerie y TipoDocumento.');
                    }

                    if (!is_null($value)) {
                        $parent = ClasificacionDocumentalTRD::find($value);

                        if (!$parent) {
                            $fail('El parent seleccionado no existe.');
                        }

                        // SubSerie solo puede tener una Serie como parent
                        if ($tipo === 'SubSerie' && $parent->tipo !== 'Serie') {
                            $fail('Las SubSeries solo pueden tener como parent una Serie.');
                        }

                        // TipoDocumento puede tener como parent una Serie o SubSerie
                        if ($tipo === 'TipoDocumento' && !in_array($parent->tipo, ['Serie', 'SubSerie'])) {
                            $fail('Los TipoDocumento solo pueden tener como parent una Serie o SubSerie.');
                        }
                    }
                }
            ],
            'dependencia_id' => 'required|exists:calidad_organigrama,id'
        ];
    }

    public function messages()
    {
        return [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser Serie, SubSerie o TipoDocumento.',
            'nom.required' => 'El nombre es obligatorio.',
            'nom.max' => 'El nombre no debe superar los 100 caracteres.',
            'cod.max' => 'El código no debe superar los 10 caracteres.',
            'cod.unique' => 'El código ya existe en el sistema.',
            'parent.required' => 'El campo parent es obligatorio para SubSerie y TipoDocumento.',
            'dependencia_id.required' => 'Debe seleccionar una dependencia válida.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
        ];
    }
}
