<?php

namespace App\Http\Requests\MiBandeja\TempReci;

use App\Services\MiBandeja\TempDocumentosRecibidos\EstilosDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfiguracionPaginaRequest extends FormRequest
{
    /** Lo que puede mostrar el encabezado o el pie; pagina = «Página n de t»; logo = el de la entidad. */
    public const ELEMENTOS_FRANJA = ['logo', 'titulo', 'autor', 'fecha', 'pagina', 'texto_libre'];

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
            'configuracion_header.elementos.*' => ['string', Rule::in(self::ELEMENTOS_FRANJA)],
            'configuracion_header.texto_libre' => ['sometimes', 'string', 'max:500'],
            'configuracion_footer' => ['sometimes', 'array', 'nullable'],
            'configuracion_footer.habilitado' => ['sometimes', 'boolean'],
            'configuracion_footer.elementos' => ['sometimes', 'array'],
            'configuracion_footer.elementos.*' => ['string', Rule::in(self::ELEMENTOS_FRANJA)],
            'configuracion_footer.texto_libre' => ['sometimes', 'string', 'max:500'],
            // Estilos modificados (Normal, Título 1-3 y Cita): solo lo que cambió; null vuelve al valor por defecto
            'configuracion_formato' => ['sometimes', 'nullable', 'array:estilos,marca_agua,color_pagina'],
            // Diseño de Word: marca de agua de texto y color de página
            'configuracion_formato.marca_agua' => ['sometimes', 'nullable', 'array:texto,diagonal'],
            'configuracion_formato.marca_agua.texto' => ['required_with:configuracion_formato.marca_agua', 'string', 'max:60'],
            'configuracion_formato.marca_agua.diagonal' => ['sometimes', 'boolean'],
            'configuracion_formato.color_pagina' => ['sometimes', 'nullable', 'string', 'regex:'.EstilosDocumento::PATRON_COLOR],
            'configuracion_formato.estilos' => ['sometimes', 'nullable', 'array:'.implode(',', EstilosDocumento::ESTILOS)],
            'configuracion_formato.estilos.*' => ['array:'.implode(',', EstilosDocumento::PROPIEDADES)],
            'configuracion_formato.estilos.*.fuente' => ['nullable', 'string', 'regex:'.EstilosDocumento::PATRON_FUENTE],
            'configuracion_formato.estilos.*.tamano' => ['nullable', 'numeric', 'min:6', 'max:96'],
            'configuracion_formato.estilos.*.color' => ['nullable', 'string', 'regex:'.EstilosDocumento::PATRON_COLOR],
            'configuracion_formato.estilos.*.negrita' => ['nullable', 'boolean'],
            'configuracion_formato.estilos.*.cursiva' => ['nullable', 'boolean'],
            'configuracion_formato.estilos.*.alineacion' => ['nullable', Rule::in(EstilosDocumento::ALINEACIONES)],
            'configuracion_formato.estilos.*.espacio_antes' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'configuracion_formato.estilos.*.espacio_despues' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'configuracion_header.elementos.*.in' => 'Los elementos del header deben ser: logo, titulo, autor, fecha, pagina o texto_libre.',
            'configuracion_footer.elementos.*.in' => 'Los elementos del footer deben ser: logo, titulo, autor, fecha, pagina o texto_libre.',
        ];
    }
}
