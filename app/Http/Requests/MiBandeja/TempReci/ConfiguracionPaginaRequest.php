<?php

namespace App\Http\Requests\MiBandeja\TempReci;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfiguracionPaginaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tamano_papel' => ['sometimes', 'string', Rule::in(['a4', 'carta', 'legal', 'oficio'])],
            'orientacion' => ['sometimes', 'string', Rule::in(['vertical', 'horizontal'])],
            'margenes' => ['sometimes', 'array'],
            'margenes.superior' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'margenes.inferior' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'margenes.izquierdo' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'margenes.derecho' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'configuracion_columnas' => ['sometimes', 'array'],
            'configuracion_columnas.numero' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'configuracion_columnas.con_linea' => ['sometimes', 'boolean'],
            'configuracion_header' => ['sometimes', 'array', 'nullable'],
            'configuracion_header.habilitado' => ['sometimes', 'boolean'],
            'configuracion_header.elementos' => ['sometimes', 'array'],
            'configuracion_header.elementos.*' => ['string', Rule::in(['titulo', 'autor', 'fecha', 'texto_libre'])],
            'configuracion_header.texto_libre' => ['sometimes', 'string', 'max:500'],
            'configuracion_footer' => ['sometimes', 'array', 'nullable'],
            'configuracion_footer.habilitado' => ['sometimes', 'boolean'],
            'configuracion_footer.elementos' => ['sometimes', 'array'],
            'configuracion_footer.elementos.*' => ['string', Rule::in(['titulo', 'autor', 'fecha', 'texto_libre'])],
            'configuracion_footer.texto_libre' => ['sometimes', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tamano_papel.in' => 'El tamaño de papel debe ser: a4, carta, legal u oficio.',
            'orientacion.in' => 'La orientación debe ser: vertical u horizontal.',
            'margenes.*.min' => 'Los márgenes deben ser mayores o iguales a 0mm.',
            'margenes.*.max' => 'Los márgenes no pueden exceder 100mm.',
            'configuracion_columnas.numero.max' => 'El documento puede tener máximo 4 columnas.',
            'configuracion_header.elementos.*.in' => 'Los elementos del header deben ser: titulo, autor, fecha o texto_libre.',
            'configuracion_footer.elementos.*.in' => 'Los elementos del footer deben ser: titulo, autor, fecha o texto_libre.',
        ];
    }
}
