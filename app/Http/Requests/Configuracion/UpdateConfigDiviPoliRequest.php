<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateConfigDiviPoliRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // La autorización se maneja a través de middleware
    }

    /**
     * Get the división política ID from route.
     *
     * @return int|null
     */
    protected function getDiviPoliId()
    {
        // Intentar obtener desde el route model binding con todos los nombres posibles
        $diviPoli = $this->route('config_divi_poli')
            ?? $this->route('divipoli')
            ?? $this->route('division_politica')
            ?? $this->route()->parameter('config_divi_poli')
            ?? $this->route()->parameter('divipoli')
            ?? $this->route()->parameter('division_politica');

        // Si es un objeto (modelo Eloquent), obtener el ID
        if (is_object($diviPoli)) {
            $id = $diviPoli->id ?? $diviPoli->getKey();
            if ($id) {
                return (int)$id;
            }
        }

        // Si es numérico, usarlo directamente
        if (is_numeric($diviPoli)) {
            return (int)$diviPoli;
        }

        // Como último recurso, intentar obtener desde los segmentos de la URL
        $segments = $this->segments();
        $divisionPoliticaIndex = array_search('division-politica', $segments);
        if ($divisionPoliticaIndex !== false && isset($segments[$divisionPoliticaIndex + 1])) {
            $id = $segments[$divisionPoliticaIndex + 1];
            if (is_numeric($id)) {
                return (int)$id;
            }
        }

        return null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Intentar obtener el modelo directamente desde la ruta
        $diviPoli = $this->route('division_politica')
            ?? $this->route('config_divi_poli')
            ?? $this->route('divipoli');

        // Obtener el ID del modelo o directamente
        $diviPoliId = null;
        if (is_object($diviPoli)) {
            $diviPoliId = $diviPoli->id ?? $diviPoli->getKey();
        } elseif (is_numeric($diviPoli)) {
            $diviPoliId = (int)$diviPoli;
        } else {
            // Como último recurso, usar el método getDiviPoliId
            $diviPoliId = $this->getDiviPoliId();
        }

        // Construir las reglas base
        $parentRules = [
            'nullable',
            'integer',
            'exists:config_divi_poli,id',
        ];

        // Agregar la regla notIn solo si tenemos un ID válido
        if ($diviPoliId !== null) {
            $parentRules[] = Rule::notIn([$diviPoliId]);
        }

        // Construir la regla unique para codigo - usar el mismo patrón que otros archivos
        $codigoRules = [
            'sometimes',
            'string',
            'max:5',
        ];

        // Asegurar que siempre tengamos un ID válido antes de usar ignore
        if ($diviPoliId !== null && is_numeric($diviPoliId) && $diviPoliId > 0) {
            // Usar una validación personalizada que verifique directamente en la BD
            $codigoRules[] = function ($attribute, $value, $fail) use ($diviPoliId) {
                if ($value) {
                    $exists = DB::table('config_divi_poli')
                        ->where('codigo', $value)
                        ->where('id', '!=', (int)$diviPoliId)
                        ->exists();

                    if ($exists) {
                        $fail('El código ya está en uso, por favor elija otro.');
                    }
                }
            };
        } else {
            $codigoRules[] = Rule::unique('config_divi_poli', 'codigo');
        }

        return [
            'parent' => $parentRules,
            'codigo' => $codigoRules,
            'nombre' => [
                'sometimes',
                'string',
                'max:70'
            ],
            'tipo' => [
                'sometimes',
                'string',
                'max:15',
                'in:Pais,Departamento,Municipio'
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
            'parent.integer' => 'El ID de la división política padre debe ser un número entero.',
            'parent.exists' => 'La división política padre seleccionada no existe.',
            'parent.not_in' => 'Una división política no puede ser su propio padre.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede tener más de 5 caracteres.',
            'codigo.unique' => 'El código ya está en uso, por favor elija otro.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 70 caracteres.',
            'tipo.string' => 'El tipo debe ser una cadena de texto.',
            'tipo.max' => 'El tipo no puede tener más de 15 caracteres.',
            'tipo.in' => 'El tipo debe ser Pais, Departamento o Municipio.',
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
            'parent' => 'división política padre',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'tipo' => 'tipo'
        ];
    }
}
