<?php

namespace App\Http\Requests\OfiArchivo;

use App\Models\Configuracion\ConfigLista;
use Database\Seeders\Configuracion\TiposPlantillaSeed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Datos de una plantilla generada (Generador de plantillas o «Guardar como plantilla»). Al crear, el nombre es
 * obligatorio; al editar, todo es opcional.
 */
class PlantillaGeneradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $creando = $this->isMethod('post');

        return [
            'nombre' => [$creando ? 'required' : 'sometimes', 'string', 'min:3', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_id' => ['nullable', 'integer', self::reglaTipo()],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** El tipo debe ser un detalle activo de la lista «Tipos de plantilla» (Configuración → Listas). */
    public static function reglaTipo(): Exists
    {
        return Rule::exists('config_listas_detalles', 'id')->where(fn ($q) => $q->where('estado', 1)
            ->whereIn('lista_id', ConfigLista::where('cod', TiposPlantillaSeed::COD_LISTA)->select('id')));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la plantilla es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede exceder 120 caracteres.',
            'tipo_id.exists' => 'El tipo de plantilla no es válido.',
        ];
    }
}
