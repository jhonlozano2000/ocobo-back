<?php

namespace App\Services\OfiArchivo;

/**
 * Plantillas institucionales de base para el Generador de plantillas (oficio, acta, memorando y circular), como
 * las plantillas de Word con membrete. Las siembra PlantillasGeneradasBaseSeed; desde ahí se editan en el
 * Generador como cualquier otra.
 *
 * El membrete (razón social y NIT; el logo va en el encabezado de página), la ciudad, la fecha y los datos de
 * quien crea el documento son variables (nodo «variable») que se llenan al crear cada documento; lo que debe
 * completar el usuario va en campos por completar (nodo «campo»: se ven resaltados y Tab pasa de uno a otro).
 * En los textos de este servicio, «[Asunto]» es un campo, «{fecha}» una variable y «{usuario_nombre|mayus}» una
 * variable en mayúsculas; enLinea() los convierte. La página es carta con los márgenes de la GTC 185 y un pie
 * con los datos de contacto de la entidad y el número de página.
 */
class PlantillasBaseService
{
    /** clave => [nombre, código del tipo en la lista TipPlanti, descripción] */
    public const PLANTILLAS = [
        'oficio' => ['Oficio', 'OFICIO', 'Comunicación oficial externa con membrete, destinatario y firma.'],
        'memorando' => ['Memorando', 'MEMORANDO', 'Comunicación interna entre dependencias.'],
        'circular' => ['Circular', 'CIRCULAR', 'Comunicación general a varios destinatarios.'],
        'acta' => ['Acta', 'ACTA', 'Acta de reunión o comité con asistentes, orden del día y compromisos.'],
    ];

    /** «[campo]», «{variable}» o «{variable|mayus}». */
    private const PATRON = '/(\[[^\]]+\]|\{[a-z_]+(?:\|mayus)?\})/u';

    /** Márgenes en mm según la GTC 185 (comunicaciones oficiales). */
    public const MARGENES = ['superior' => 30, 'inferior' => 25, 'izquierdo' => 30, 'derecho' => 20];

    /** Datos de contacto del pie; los que no estén configurados se quitan al crear el documento. */
    public const TEXTO_PIE = '{entidad_direccion} · Tel. {entidad_telefono} · {entidad_correo} · {entidad_web}';

    /**
     * @return array{nombre: string, tipo: string, descripcion: string, contenido: array, configuracion: array}
     */
    public function construir(string $plantilla): array
    {
        [$nombre, $tipo, $descripcion] = self::PLANTILLAS[$plantilla];

        $cuerpo = match ($plantilla) {
            'oficio' => $this->oficio(),
            'acta' => $this->acta(),
            'memorando' => $this->memorando(),
            'circular' => $this->circular(),
        };

        return [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'contenido' => ['type' => 'doc', 'content' => [...$this->membrete(), ...$cuerpo]],
            'configuracion' => [
                'tamano_papel' => 'carta',
                'orientacion' => 'vertical',
                'margenes' => self::MARGENES,
                'configuracion_header' => ['habilitado' => true, 'elementos' => ['logo']],
                'configuracion_footer' => [
                    'habilitado' => true,
                    'elementos' => ['texto_libre', 'pagina'],
                    'texto_libre' => self::TEXTO_PIE,
                ],
            ],
        ];
    }

    private function oficio(): array
    {
        return [
            $this->parrafo('{ciudad}, {fecha}'),
            $this->vacio(),
            $this->parrafo('Señor(a)'),
            $this->parrafo($this->enLinea('[Nombre del destinatario]', true)),
            $this->parrafo('[Cargo]'),
            $this->parrafo('[Entidad]'),
            $this->parrafo('[Dirección]'),
            $this->parrafo('[Ciudad]'),
            $this->vacio(),
            $this->parrafo([$this->texto('Asunto: ', true), ...$this->enLinea('[Asunto]')]),
            $this->vacio(),
            $this->parrafo('Respetado(a) señor(a):'),
            $this->vacio(),
            $this->parrafo('[Escriba aquí el texto del oficio.]', 'justify'),
            $this->vacio(),
            $this->parrafo('Atentamente,'),
            ...$this->firma(),
        ];
    }

    private function memorando(): array
    {
        return [
            $this->titulo('MEMORANDO'),
            $this->parrafo('{ciudad}, {fecha}'),
            $this->vacio(),
            $this->campo('PARA', '[Nombre y cargo del destinatario]'),
            $this->campo('DE', '{usuario_nombre}, {usuario_cargo}'),
            $this->campo('ASUNTO', '[Asunto]'),
            $this->vacio(),
            $this->parrafo('[Escriba aquí el texto del memorando.]', 'justify'),
            $this->vacio(),
            $this->parrafo('Cordialmente,'),
            ...$this->firma(),
        ];
    }

    private function circular(): array
    {
        return [
            $this->titulo('CIRCULAR No. [número]'),
            $this->parrafo('{ciudad}, {fecha}'),
            $this->vacio(),
            $this->campo('PARA', '[Destinatarios]'),
            $this->campo('DE', '{usuario_dependencia}'),
            $this->campo('ASUNTO', '[Asunto]'),
            $this->vacio(),
            $this->parrafo('[Escriba aquí el texto de la circular.]', 'justify'),
            $this->vacio(),
            $this->parrafo('Cordialmente,'),
            ...$this->firma(),
        ];
    }

    private function acta(): array
    {
        return [
            $this->titulo('ACTA No. [número]'),
            $this->parrafo($this->enLinea('[Nombre del comité o reunión]', true), 'center'),
            $this->vacio(),
            $this->campo('Fecha', '{fecha}'),
            $this->campo('Hora', '[hh:mm]'),
            $this->campo('Lugar', '[Lugar]'),
            $this->subtitulo('Asistentes'),
            $this->tabla(['Nombre', 'Cargo', 'Dependencia'], [
                ['{usuario_nombre}', '{usuario_cargo}', '{usuario_dependencia}'],
                ['', '', ''],
            ]),
            $this->subtitulo('Orden del día'),
            [
                'type' => 'orderedList',
                'attrs' => ['start' => 1],
                'content' => array_map(
                    fn ($tema) => ['type' => 'listItem', 'content' => [$this->parrafo($tema)]],
                    ['Verificación del quórum.', '[Tema]', 'Proposiciones y varios.']
                ),
            ],
            $this->subtitulo('Desarrollo'),
            $this->parrafo('[Describa aquí el desarrollo de cada punto del orden del día.]', 'justify'),
            $this->subtitulo('Compromisos'),
            $this->tabla(['Compromiso', 'Responsable', 'Fecha'], [['', '', '']]),
            $this->vacio(),
            $this->parrafo('En constancia se firma por:'),
            $this->vacio(),
            $this->vacio(),
            $this->tabla(null, [
                ['________________________', '________________________'],
                ['[Presidente]', '[Secretario]'],
            ]),
        ];
    }

    /** Razón social y NIT, centrados. */
    private function membrete(): array
    {
        return [
            $this->parrafo($this->enLinea('{entidad_nombre|mayus}', true), 'center'),
            $this->parrafo('NIT {entidad_nit}', 'center'),
            ['type' => 'horizontalRule'],
        ];
    }

    private function firma(): array
    {
        return [
            $this->vacio(),
            $this->vacio(),
            $this->parrafo($this->enLinea('{usuario_nombre|mayus}', true)),
            $this->parrafo('{usuario_cargo}'),
            $this->parrafo('{usuario_dependencia}'),
        ];
    }

    private function texto(string $texto, bool $negrita = false): array
    {
        $nodo = ['type' => 'text', 'text' => $texto];

        return $negrita ? $nodo + ['marks' => [['type' => 'bold']]] : $nodo;
    }

    /**
     * Nodos en línea de un texto: «[...]» pasa a ser un campo por completar y «{...}» una variable.
     *
     * @return list<array>
     */
    private function enLinea(string $texto, bool $negrita = false): array
    {
        $nodos = [];
        foreach (preg_split(self::PATRON, $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $parte) {
            if (preg_match('/^\[(.+)\]$/u', $parte, $m)) {
                $nodo = ['type' => 'campo', 'attrs' => ['etiqueta' => trim($m[1])]];
            } elseif (preg_match('/^\{([a-z_]+)(\|mayus)?\}$/u', $parte, $m)) {
                $nodo = ['type' => 'variable', 'attrs' => ['clave' => $m[1], 'mayusculas' => isset($m[2])]];
            } else {
                $nodo = $this->texto($parte);
            }
            $nodos[] = $negrita ? $nodo + ['marks' => [['type' => 'bold']]] : $nodo;
        }

        return $nodos;
    }

    private function parrafo(string|array $contenido, ?string $alineacion = null): array
    {
        $nodo = ['type' => 'paragraph', 'content' => is_string($contenido) ? $this->enLinea($contenido) : $contenido];

        return $alineacion ? $nodo + ['attrs' => ['textAlign' => $alineacion]] : $nodo;
    }

    private function vacio(): array
    {
        return ['type' => 'paragraph'];
    }

    private function campo(string $etiqueta, string $valor): array
    {
        return $this->parrafo([$this->texto($etiqueta.': ', true), ...$this->enLinea($valor)]);
    }

    private function titulo(string $texto): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'center'], 'content' => $this->enLinea($texto)];
    }

    private function subtitulo(string $texto): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [$this->texto($texto)]];
    }

    /** Tabla con encabezado opcional; las celdas vacías llevan un párrafo vacío, como las crea el editor. */
    private function tabla(?array $encabezado, array $filas): array
    {
        $fila = fn (array $celdas, string $tipo) => [
            'type' => 'tableRow',
            'content' => array_map(
                fn ($celda) => ['type' => $tipo, 'content' => [$celda === '' ? $this->vacio() : $this->parrafo($celda)]],
                $celdas
            ),
        ];

        return [
            'type' => 'table',
            'content' => [
                ...($encabezado ? [$fila($encabezado, 'tableHeader')] : []),
                ...array_map(fn ($celdas) => $fila($celdas, 'tableCell'), $filas),
            ],
        ];
    }
}
