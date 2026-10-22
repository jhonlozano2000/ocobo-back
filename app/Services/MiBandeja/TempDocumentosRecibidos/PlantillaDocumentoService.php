<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Plantillas institucionales para Mis Documentos (oficio, acta, memorando y circular), como las plantillas de
 * Word con membrete.
 *
 * Arma el contenido inicial (JSON de TipTap) con el membrete de la entidad (logo, razón social y NIT), la ciudad
 * y la fecha, y el nombre, cargo y dependencia de quien lo crea; lo que debe completar el usuario va en campos
 * por completar (nodo «campo»: se ven resaltados y Tab pasa de uno a otro). En los textos de este servicio se
 * escriben entre corchetes y enLinea() los convierte. También da la configuración de página: tamaño carta con los márgenes de la GTC 185 y un pie con los
 * datos de contacto de la entidad y el número de página.
 */
class PlantillaDocumentoService
{
    public const PLANTILLAS = ['oficio', 'acta', 'memorando', 'circular'];

    /** Lo que va entre corchetes en los textos de las plantillas es un campo por completar. */
    private const PATRON_CAMPO = '/\[([^\]]+)\]/u';

    /** Márgenes en mm según la GTC 185 (comunicaciones oficiales). */
    public const MARGENES = ['superior' => 30, 'inferior' => 25, 'izquierdo' => 30, 'derecho' => 20];

    /** Logos más pesados no se incrustan: el contenido del documento viaja completo en cada guardado. */
    private const LOGO_MAX_BYTES = 300 * 1024;

    /**
     * Configuración de página y contenido inicial de la plantilla.
     *
     * @return array{pagina: array, contenido: array}
     */
    public function construir(string $plantilla, User $user): array
    {
        $empresa = ConfigVarias::getInformacionEmpresa();
        $datos = [
            'empresa' => $empresa,
            'ciudad' => $this->ciudad($empresa['divi_poli_id_empresa'] ?? null),
            'fecha' => Carbon::now()->locale('es')->translatedFormat('j \d\e F \d\e Y'),
            'nombre' => trim(($user->nombres ?? '').' '.($user->apellidos ?? '')),
            'cargo' => (string) ($user->cargo?->nombre ?? ''),
            'dependencia' => (string) ($user->cargo?->dependencia?->nombre ?? ''),
        ];

        $cuerpo = match ($plantilla) {
            'oficio' => $this->oficio($datos),
            'acta' => $this->acta($datos),
            'memorando' => $this->memorando($datos),
            'circular' => $this->circular($datos),
        };

        return [
            'pagina' => [
                'tamano_papel' => 'carta',
                'orientacion' => 'vertical',
                'margenes' => self::MARGENES,
                'configuracion_footer' => $this->pie($empresa),
            ],
            'contenido' => ['type' => 'doc', 'content' => [...$this->membrete($empresa), ...$cuerpo]],
        ];
    }

    private function oficio(array $d): array
    {
        return [
            $this->parrafo($this->ciudadFecha($d)),
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
            ...$this->firma($d),
        ];
    }

    private function memorando(array $d): array
    {
        return [
            $this->titulo('MEMORANDO'),
            $this->parrafo($this->ciudadFecha($d)),
            $this->vacio(),
            $this->campo('PARA', '[Nombre y cargo del destinatario]'),
            $this->campo('DE', $this->remitente($d)),
            $this->campo('ASUNTO', '[Asunto]'),
            $this->vacio(),
            $this->parrafo('[Escriba aquí el texto del memorando.]', 'justify'),
            $this->vacio(),
            $this->parrafo('Cordialmente,'),
            ...$this->firma($d),
        ];
    }

    private function circular(array $d): array
    {
        return [
            $this->titulo('CIRCULAR No. [número]'),
            $this->parrafo($this->ciudadFecha($d)),
            $this->vacio(),
            $this->campo('PARA', '[Destinatarios]'),
            $this->campo('DE', $d['dependencia'] ?: $this->remitente($d)),
            $this->campo('ASUNTO', '[Asunto]'),
            $this->vacio(),
            $this->parrafo('[Escriba aquí el texto de la circular.]', 'justify'),
            $this->vacio(),
            $this->parrafo('Cordialmente,'),
            ...$this->firma($d),
        ];
    }

    private function acta(array $d): array
    {
        return [
            $this->titulo('ACTA No. [número]'),
            $this->parrafo($this->enLinea('[Nombre del comité o reunión]', true), 'center'),
            $this->vacio(),
            $this->campo('Fecha', $d['fecha']),
            $this->campo('Hora', '[hh:mm]'),
            $this->campo('Lugar', '[Lugar]'),
            $this->subtitulo('Asistentes'),
            $this->tabla(['Nombre', 'Cargo', 'Dependencia'], [
                [$d['nombre'], $d['cargo'], $d['dependencia']],
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

    /** Logo (si hay y no es muy pesado), razón social y NIT, centrados. */
    private function membrete(array $empresa): array
    {
        $bloques = [];
        if ($logo = $this->logo($empresa['logo_empresa'] ?? null)) {
            $bloques[] = ['type' => 'image', 'attrs' => ['src' => $logo, 'alt' => (string) ($empresa['razon_social_empresa'] ?? '')]];
        }
        if (! empty($empresa['razon_social_empresa'])) {
            $bloques[] = $this->parrafo([$this->texto(mb_strtoupper($empresa['razon_social_empresa']), true)], 'center');
        }
        if (! empty($empresa['nit_empresa'])) {
            $bloques[] = $this->parrafo('NIT '.$empresa['nit_empresa'], 'center');
        }
        if ($bloques) {
            $bloques[] = ['type' => 'horizontalRule'];
        }

        return $bloques;
    }

    /** Pie de cada página con los datos de contacto de la entidad. */
    private function pie(array $empresa): array
    {
        $contacto = array_filter([
            $empresa['direccion_empresa'] ?? null,
            ! empty($empresa['telefono_empresa']) ? 'Tel. '.$empresa['telefono_empresa'] : null,
            $empresa['correo_electronico_empresa'] ?? null,
            $empresa['web_empresa'] ?? null,
        ]);

        return [
            'habilitado' => true,
            'elementos' => $contacto ? ['texto_libre', 'pagina'] : ['pagina'],
            'texto_libre' => implode(' · ', $contacto),
        ];
    }

    private function firma(array $d): array
    {
        return [
            $this->vacio(),
            $this->vacio(),
            $this->parrafo($this->enLinea($d['nombre'] ? mb_strtoupper($d['nombre']) : '[Nombre]', true)),
            $this->parrafo($d['cargo'] ?: '[Cargo]'),
            ...($d['dependencia'] ? [$this->parrafo($d['dependencia'])] : []),
        ];
    }

    private function remitente(array $d): string
    {
        return implode(', ', array_filter([$d['nombre'], $d['cargo']])) ?: '[Remitente]';
    }

    private function ciudadFecha(array $d): string
    {
        return ($d['ciudad'] ?: '[Ciudad]').', '.$d['fecha'];
    }

    private function ciudad(mixed $diviPoliId): string
    {
        return $diviPoliId ? (string) (ConfigDiviPoli::find($diviPoliId)?->nombre ?? '') : '';
    }

    /** El logo como data URI: está en un disco privado que ni el navegador ni dompdf pueden pedir por URL. */
    private function logo(?string $archivo): ?string
    {
        $disco = Storage::disk('otros_archivos');
        if (! $archivo || ! $disco->exists($archivo) || $disco->size($archivo) > self::LOGO_MAX_BYTES) {
            return null;
        }
        // Por el contenido, no por la extensión: un archivo que no es imagen no se incrusta
        $bytes = $disco->get($archivo);
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
    }

    private function texto(string $texto, bool $negrita = false): array
    {
        $nodo = ['type' => 'text', 'text' => $texto];

        return $negrita ? $nodo + ['marks' => [['type' => 'bold']]] : $nodo;
    }

    /**
     * Nodos en línea de un texto: lo que va entre corchetes pasa a ser un campo por completar.
     *
     * @return list<array>
     */
    private function enLinea(string $texto, bool $negrita = false): array
    {
        $nodos = [];
        foreach (preg_split(self::PATRON_CAMPO, $texto, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $parte) {
            if ($parte === '') {
                continue;
            }
            $nodo = $i % 2 ? ['type' => 'campo', 'attrs' => ['etiqueta' => trim($parte)]] : $this->texto($parte);
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
