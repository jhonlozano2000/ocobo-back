<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Services\OfiArchivo\VariablesPlantillaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

class DocumentoExportService
{
    /** Marcador del total de hojas en el pie del PDF; se reemplaza tras contar las hojas. */
    private const TOTAL_PAGINAS = '__TOTAL_PAGINAS__';

    /** Un nivel de sangría del editor: 0,5" = 36 pt. */
    private const SANGRIA_PT = 36;

    /** Alto máximo del logo del encabezado o pie, en pt; con márgenes estrechos, el 70 % del margen. */
    private const LOGO_ALTO_PT = 54;

    /** Fondo de los campos por completar de las plantillas (el amarillo suave del editor). */
    private const COLOR_CAMPO = '#ffe9a8';

    /**
     * Estilos de lista del editor (EstiloLista): list-style-type del HTML (null: el marcador se escribe a mano
     * porque dompdf no lo dibuja) y formato y texto del nivel en la numeración de Word.
     */
    private const ESTILOS_LISTA = [
        'disco' => ['disc', 'bullet', '•'],
        'circulo' => ['circle', 'bullet', '○'],
        'cuadrado' => ['square', 'bullet', '■'],
        'check' => [null, 'bullet', '✓'],
        'flecha' => [null, 'bullet', '➢'],
        'guion' => [null, 'bullet', '–'],
        'decimal' => ['decimal', 'decimal', '%.'],
        'parentesis' => [null, 'decimal', '%)'],
        'letra-minuscula' => ['lower-alpha', 'lowerLetter', '%.'],
        'letra-mayuscula' => ['upper-alpha', 'upperLetter', '%.'],
        'romano-minuscula' => ['lower-roman', 'lowerRoman', '%.'],
        'romano-mayuscula' => ['upper-roman', 'upperRoman', '%.'],
        'multinivel' => [null, 'decimal', '%.'],
    ];

    /** Viñetas por nivel de una lista sin estilo, como las alterna el navegador. */
    private const VINETAS_POR_NIVEL = ['disco', 'circulo', 'cuadrado'];

    /** Numeración de los niveles exteriores mientras se escribe una lista multinivel en HTML. */
    private ?array $prefijoMultinivel = null;

    /** Marcador escrito a mano que lleva el primer párrafo del elemento de lista actual. */
    private ?string $marcadorLista = null;

    /** Documento de PhpWord en exportación (para registrar las numeraciones de las listas). */
    private ?PhpWord $phpWord = null;

    private int $listasDocx = 0;

    public function __construct(private LogoEntidadService $logos = new LogoEntidadService) {}

    public function exportarPdf(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $configPagina = $documento->getConfiguracionPagina();
        $creadorNombre = $this->nombreCreador($documento);

        $html = $this->contenidoToHtml($contenido->contenido_yjs ?? []);
        $contenidoHtml = $this->wrapHtmlForPdf(
            $documento->titulo,
            $html,
            $configPagina,
            $creadorNombre
        );

        $pdf = $this->renderizarPdf($contenidoHtml, $configPagina);
        // dompdf no conoce el total de hojas al dibujar el pie: una primera pasada las cuenta
        if (str_contains($contenidoHtml, self::TOTAL_PAGINAS)) {
            $pdf->output();
            $total = $pdf->getDomPDF()->getCanvas()->get_page_count();
            $pdf = $this->renderizarPdf(str_replace(self::TOTAL_PAGINAS, (string) $total, $contenidoHtml), $configPagina);
        }

        $filename = 'documento_'.$documento->id.'_'.time().'.pdf';
        $path = 'exports/'.$filename;

        Storage::disk('local')->makeDirectory('exports');
        Storage::disk('local')->put($path, $pdf->output());

        return Storage::disk('local')->path($path);
    }

    private function renderizarPdf(string $html, array $configPagina): \Barryvdh\DomPDF\PDF
    {
        $papel = match ($configPagina['tamano_papel']) {
            'carta' => 'letter',
            'legal' => 'legal',
            // dompdf mide en puntos: 215,9 × 330,2 mm
            'oficio' => [0, 0, 612, 936],
            default => 'a4',
        };
        $orientacion = $configPagina['orientacion'] === 'horizontal' ? 'landscape' : 'portrait';

        return Pdf::loadHTML($html)
            ->setPaper($papel, $orientacion)
            ->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif']);
    }

    private function wrapHtmlForPdf(string $titulo, string $contenido, array $config, string $autor): string
    {
        $tituloHtml = $this->esc($titulo);
        $margenes = $config['margenes'] ?? ['superior' => 25.4, 'inferior' => 25.4, 'izquierdo' => 25.4, 'derecho' => 25.4];
        $mmToPt = 2.83465;

        $marginTop = round($margenes['superior'] * $mmToPt);
        $marginBottom = round($margenes['inferior'] * $mmToPt);
        $marginLeft = round($margenes['izquierdo'] * $mmToPt);
        $marginRight = round($margenes['derecho'] * $mmToPt);

        $headerPartes = $this->partesFranja($config['header'] ?? null, $titulo, $autor, true, $marginTop);
        $footerPartes = $this->partesFranja($config['footer'] ?? null, $titulo, $autor, true, $marginBottom);

        $columns = $config['columnas'] ?? ['numero' => 1];
        $columnCss = '';
        if (($columns['numero'] ?? 1) > 1) {
            $columnCount = $columns['numero'];
            $columnRule = ! empty($columns['con_linea']) ? '1px solid #ccc' : 'none';
            $columnCss = "-webkit-column-count: {$columnCount}; -moz-column-count: {$columnCount}; column-count: {$columnCount}; -webkit-column-rule: {$columnRule}; -moz-column-rule: {$columnRule}; column-rule: {$columnRule}; -webkit-column-gap: 32px; -moz-column-gap: 32px; column-gap: 32px;";
        }

        // Encabezado y pie fijos: dompdf los repite en cada hoja, dentro de los márgenes superior e inferior
        $headerBlock = $headerPartes
            ? "<table class='franja' style='top: -{$marginTop}pt; height: {$marginTop}pt;'><tr><td>".implode(' &nbsp;|&nbsp; ', $headerPartes).'</td></tr></table>'
            : '';
        $footerBlock = $footerPartes
            ? "<table class='franja' style='bottom: -{$marginBottom}pt; height: {$marginBottom}pt;'><tr><td>".implode(' &nbsp;|&nbsp; ', $footerPartes).'</td></tr></table>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$tituloHtml}</title>
    <style>
        @page {
            margin-top: {$marginTop}pt;
            margin-bottom: {$marginBottom}pt;
            margin-left: {$marginLeft}pt;
            margin-right: {$marginRight}pt;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #222;
        }
        h1 { font-size: 18pt; font-weight: bold; margin: 16pt 0 8pt 0; }
        h2 { font-size: 14pt; font-weight: bold; margin: 12pt 0 6pt 0; }
        h3 { font-size: 12pt; font-weight: bold; margin: 10pt 0 5pt 0; }
        p { margin: 0 0 8pt 0; text-align: justify; }
        strong, b { font-weight: bold; }
        em, i { font-style: italic; }
        u { text-decoration: underline; }
        s { text-decoration: line-through; }
        code { font-family: 'Courier New', monospace; font-size: 10pt; background: #f4f4f4; padding: 1pt 3pt; }
        pre { font-family: 'Courier New', monospace; font-size: 10pt; background: #f4f4f4; padding: 8pt; border-radius: 3pt; }
        blockquote { border-left: 3pt solid #ccc; padding-left: 12pt; margin-left: 0; font-style: italic; color: #555; }
        hr { border: none; border-top: 1pt solid #ccc; margin: 12pt 0; }
        ul, ol { margin-left: 18pt; }
        li { margin-bottom: 4pt; }
        table { border-collapse: collapse; width: 100%; margin: 8pt 0; }
        th, td { border: 0.5pt solid #aaa; padding: 6pt; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        img { max-width: 100%; height: auto; }
        .contenido-pagina {
            {$columnCss}
        }
        mark { background-color: #ffeb3b; padding: 0 2pt; }
        table.franja { position: fixed; left: 0; right: 0; width: 100%; margin: 0; border: none; }
        table.franja td { border: none; padding: 0 0; text-align: center; vertical-align: middle; font-size: 9pt; color: #666; }
        .numero-pagina:after { content: "Página " counter(page) " de __TOTAL_PAGINAS__"; }
    </style>
</head>
<body>
    {$headerBlock}
    {$footerBlock}
    <div class="contenido-pagina">
        {$contenido}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Textos del encabezado o pie en el orden elegido. El número de página va como marcador: en el PDF es un
     * contador CSS de dompdf y en el .docx los campos PAGE/NUMPAGES de Word.
     *
     * @param  array|null  $franja  {habilitado, elementos[], texto_libre}
     * @param  bool  $html  true: texto escapado para el PDF; false: texto plano para PhpWord (sin el logo, que va
     *                      como imagen aparte)
     * @param  float  $altoPt  alto de la franja (el margen), para que el logo quepa
     * @return string[]
     */
    private function partesFranja(?array $franja, string $titulo, string $autor, bool $html, float $altoPt = 0): array
    {
        if (empty($franja['habilitado'])) {
            return [];
        }

        $partes = [];
        foreach ($franja['elementos'] ?? [] as $elemento) {
            $valor = match ($elemento) {
                'titulo' => $titulo,
                'autor' => $autor,
                'fecha' => date('d/m/Y'),
                'texto_libre' => $franja['texto_libre'] ?? '',
                default => '',
            };
            if ($elemento === 'pagina') {
                $partes[] = $html ? '<span class="numero-pagina"></span>' : 'Página {PAGE} de {NUMPAGES}';
            } elseif ($elemento === 'logo') {
                if ($html && ($img = $this->imagenLogo($altoPt))) {
                    $partes[] = $img;
                }
            } elseif ($valor !== '') {
                // En el .docx las llaves del texto se tomarían por campos de Word
                $partes[] = $html ? $this->esc($valor) : str_replace(['{', '}'], ['(', ')'], $valor);
            }
        }

        return $partes;
    }

    /** Medidas del logo en pt para una franja de ese alto, o null si no hay logo. */
    private function medidasLogo(float $altoPt): ?array
    {
        $logo = $this->logos->archivo();
        if (! $logo || $logo['alto'] < 1) {
            return null;
        }
        $alto = round(min(self::LOGO_ALTO_PT, max(12, $altoPt * 0.7)), 1);

        return ['alto' => $alto, 'ancho' => round($alto * $logo['ancho'] / $logo['alto'], 1), 'bytes' => $logo['bytes']];
    }

    private function imagenLogo(float $altoPt): ?string
    {
        $medidas = $this->medidasLogo($altoPt);

        return $medidas
            ? '<img src="'.$this->logos->dataUri().'" alt="" style="width: '.$medidas['ancho'].'pt; height: '.$medidas['alto'].'pt; vertical-align: middle;">'
            : null;
    }

    private function nombreCreador(Documento $documento): string
    {
        $creador = $documento->creador;

        return $creador ? (trim($creador->nombres.' '.$creador->apellidos) ?: (string) $creador->email) : '';
    }

    public function exportarDocx(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $config = $documento->getConfiguracionPagina();
        $phpWord = new PhpWord;
        $this->phpWord = $phpWord;

        $section = $phpWord->addSection();
        foreach (['header' => ['addHeader', 'superior'], 'footer' => ['addFooter', 'inferior']] as $clave => [$metodo, $margen]) {
            $franja = $config[$clave] ?? null;
            $partes = $this->partesFranja($franja, (string) $documento->titulo, $this->nombreCreador($documento), false);
            $logo = ! empty($franja['habilitado']) && in_array('logo', $franja['elementos'] ?? [], true)
                ? $this->medidasLogo((float) ($config['margenes'][$margen] ?? 25.4) * 2.83465)
                : null;
            if (! $partes && ! $logo) {
                continue;
            }
            $hf = $section->{$metodo}();
            if ($logo) {
                $hf->addImage($logo['bytes'], ['width' => $logo['ancho'], 'height' => $logo['alto'], 'unit' => 'pt', 'alignment' => 'center']);
            }
            if ($partes) {
                $hf->addPreserveText(implode('  |  ', $partes), ['size' => 9, 'color' => '666666'], ['alignment' => 'center']);
            }
        }
        $this->agregarContenidoASection($section, $this->bloques($contenido->contenido_yjs ?? []));

        $filename = 'documento_'.$documento->id.'_'.time().'.docx';
        $path = 'exports/'.$filename;

        Storage::disk('local')->makeDirectory('exports');

        // Sin esto PhpWord escribe el texto tal cual y un «<» o un «&» del documento dañan el .docx
        $escapar = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save(Storage::disk('local')->path($path));
        } finally {
            Settings::setOutputEscapingEnabled($escapar);
        }

        return Storage::disk('local')->path($path);
    }

    public function exportarHtml(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $html = $this->contenidoToHtml($contenido->contenido_yjs ?? []);

        $fullHtml = $this->wrapHtmlDocument($documento->titulo, $html);

        $filename = 'documento_'.$documento->id.'_'.time().'.html';
        $path = 'exports/'.$filename;

        Storage::disk('local')->put($path, $fullHtml);

        return Storage::disk('local')->path($path);
    }

    public function exportarTxt(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $texto = $this->contenidoToText($contenido->contenido_yjs ?? []);

        $filename = 'documento_'.$documento->id.'_'.time().'.txt';
        $path = 'exports/'.$filename;

        Storage::disk('local')->put($path, $texto);

        return Storage::disk('local')->path($path);
    }

    /**
     * Bloques de primer nivel del documento. El editor guarda el JSON de TipTap
     * ({type: doc, content: [...]}); se acepta también una lista de bloques suelta.
     *
     * @return array<int, array>
     */
    private function bloques(array $contenido): array
    {
        $bloques = ($contenido['type'] ?? null) === 'doc' ? ($contenido['content'] ?? []) : $contenido;

        return array_values(array_filter($bloques, 'is_array'));
    }

    private function contenidoToHtml(array $contenido): string
    {
        $html = $this->renderHijos($this->bloques($contenido));

        return $html !== '' ? $html : '<p></p>';
    }

    private function renderHijos(array $nodos): string
    {
        return implode('', array_map(fn ($nodo) => is_array($nodo) ? $this->renderNode($nodo) : '', $nodos));
    }

    /**
     * Convierte un nodo de TipTap a HTML. Todo texto y atributo que viene del documento se escapa:
     * el HTML exportado se abre en el navegador y el PDF lo interpreta dompdf.
     */
    private function renderNode(array $node): string
    {
        $hijos = fn () => $this->renderHijos($node['content'] ?? []);

        switch ($node['type'] ?? null) {
            case 'paragraph':
                return '<p'.$this->estiloBloque($node).'>'.$this->tomarMarcador().$this->renderInlineContent($node['content'] ?? []).'</p>';
            case 'heading':
                $level = min(max((int) ($node['attrs']['level'] ?? 1), 1), 6);

                return "<h{$level}".$this->estiloBloque($node).'>'.$this->tomarMarcador().$this->renderInlineContent($node['content'] ?? [])."</h{$level}>";
            case 'bulletList':
            case 'orderedList':
                return $this->renderLista($node);
            case 'taskList':
                return '<ul style="list-style: none;">'.$hijos().'</ul>';
            case 'listItem':
                return '<li>'.$hijos().'</li>';
            case 'taskItem':
                $checked = ! empty($node['attrs']['checked']) ? ' checked' : '';

                return '<li><input type="checkbox"'.$checked.' disabled> '.$hijos().'</li>';
            case 'blockquote':
                return '<blockquote>'.$hijos().'</blockquote>';
            case 'codeBlock':
                return '<pre><code>'.$this->renderInlineContent($node['content'] ?? []).'</code></pre>';
            case 'horizontalRule':
                return '<hr>';
            case 'pageBreak':
                return '<div style="page-break-after: always;"></div>';
            case 'table':
                return '<table border="1" cellpadding="5" cellspacing="0">'.$hijos().'</table>';
            case 'tableRow':
                return '<tr>'.$hijos().'</tr>';
            case 'tableHeader':
                return '<th>'.$hijos().'</th>';
            case 'tableCell':
                return '<td>'.$hijos().'</td>';
            case 'image':
                $src = (string) ($node['attrs']['src'] ?? '');
                if (! preg_match('#^(https?://|data:image/)#i', $src)) {
                    return '';
                }

                return '<img src="'.$this->esc($src).'" alt="'.$this->esc($node['attrs']['alt'] ?? '').'" style="max-width: 100%;">';
            case 'text':
            case 'hardBreak':
                return $this->renderInlineContent([$node]);
            default:
                return $hijos();
        }
    }

    /**
     * Lista con el estilo de la biblioteca de viñetas y numeración. Los marcadores que dompdf no sabe dibujar
     * (✓, ➢, –, 1) y la multinivel 1.1.1) se escriben al inicio del primer párrafo de cada elemento.
     */
    private function renderLista(array $node): string
    {
        $etiqueta = $node['type'] === 'orderedList' ? 'ol' : 'ul';
        $estilo = $this->estiloLista($node);
        $multinivel = $etiqueta === 'ol' && ($estilo === 'multinivel' || ($estilo === null && $this->prefijoMultinivel !== null));
        $previo = $this->prefijoMultinivel;

        if (! $multinivel && ($estilo === null || self::ESTILOS_LISTA[$estilo][0] !== null)) {
            // Las listas anidadas en esta no siguen la numeración multinivel de una exterior
            $this->prefijoMultinivel = null;
            $html = $this->renderHijos($node['content'] ?? []);
            $this->prefijoMultinivel = $previo;
            $style = $estilo ? ' style="list-style-type: '.self::ESTILOS_LISTA[$estilo][0].';"' : '';

            return "<{$etiqueta}{$style}>{$html}</{$etiqueta}>";
        }

        $inicio = $this->inicioLista($node);
        $html = '';
        foreach (array_values(array_filter($node['content'] ?? [], 'is_array')) as $i => $item) {
            $numero = $inicio + $i;
            if ($multinivel) {
                $this->prefijoMultinivel = [...($previo ?? []), $numero];
                $this->marcadorLista = implode('.', $this->prefijoMultinivel).'.';
            } else {
                $this->marcadorLista = $estilo === 'parentesis' ? "{$numero})" : self::ESTILOS_LISTA[$estilo][2];
            }
            $html .= $this->renderNode($item);
            $this->marcadorLista = null;
        }
        $this->prefijoMultinivel = $previo;

        return "<{$etiqueta} style=\"list-style: none;\">{$html}</{$etiqueta}>";
    }

    private function tomarMarcador(): string
    {
        $marcador = $this->marcadorLista;
        $this->marcadorLista = null;

        return $marcador === null ? '' : '<span class="marcador-lista">'.$this->esc($marcador).'</span> ';
    }

    /** Estilo de la biblioteca guardado en la lista, o null si no hay o no es uno conocido. */
    private function estiloLista(array $lista): ?string
    {
        $estilo = $lista['attrs']['estiloLista'] ?? null;
        if (! is_string($estilo) || ! isset(self::ESTILOS_LISTA[$estilo])) {
            return null;
        }
        $deNumeracion = self::ESTILOS_LISTA[$estilo][1] !== 'bullet';

        return $deNumeracion === ($lista['type'] === 'orderedList') ? $estilo : null;
    }

    private function inicioLista(array $lista): int
    {
        $inicio = $lista['attrs']['start'] ?? 1;

        return is_numeric($inicio) ? max(0, min((int) $inicio, 9999)) : 1;
    }

    private function renderInlineContent(array $content): string
    {
        $html = '';

        foreach ($content as $inline) {
            if (! is_array($inline) || ! isset($inline['type'])) {
                continue;
            }

            if ($inline['type'] === 'hardBreak') {
                $html .= '<br>';

                continue;
            }

            $text = in_array($inline['type'], ['campo', 'variable'], true)
                ? '<span style="background-color: '.self::COLOR_CAMPO.';">'.$this->esc($this->textoCampo($inline)).'</span>'
                : $this->esc($inline['text'] ?? '');

            foreach ($inline['marks'] ?? [] as $mark) {
                switch ($mark['type'] ?? null) {
                    case 'bold':
                        $text = '<strong>'.$text.'</strong>';
                        break;
                    case 'italic':
                        $text = '<em>'.$text.'</em>';
                        break;
                    case 'underline':
                        $text = '<u>'.$text.'</u>';
                        break;
                    case 'strike':
                        $text = '<s>'.$text.'</s>';
                        break;
                    case 'code':
                        $text = '<code>'.$text.'</code>';
                        break;
                    case 'subscript':
                        $text = '<sub>'.$text.'</sub>';
                        break;
                    case 'superscript':
                        $text = '<sup>'.$text.'</sup>';
                        break;
                    case 'highlight':
                        $color = $this->colorCss($mark['attrs']['color'] ?? null) ?? 'yellow';
                        $text = '<span style="background-color: '.$this->esc($color).';">'.$text.'</span>';
                        break;
                    case 'insercion':
                        // Control de cambios pendiente: se exporta con marcado, como lo imprime Word
                        $text = '<ins style="color: #2e7d32; text-decoration: underline;">'.$text.'</ins>';
                        break;
                    case 'eliminacion':
                        $text = '<del style="color: #c62828; text-decoration: line-through;">'.$text.'</del>';
                        break;
                    case 'link':
                        $text = '<a href="'.$this->esc($this->hrefSeguro($mark['attrs']['href'] ?? '')).'">'.$text.'</a>';
                        break;
                    case 'textStyle':
                        $estilos = array_filter([
                            'color' => $this->colorCss($mark['attrs']['color'] ?? null),
                            'font-family' => $this->valorCss($mark['attrs']['fontFamily'] ?? null, '/^[\w\s,\'"-]+$/u'),
                            'font-size' => $this->valorCss($mark['attrs']['fontSize'] ?? null, '/^\d+(\.\d+)?(pt|px|em|rem|%)$/'),
                        ]);
                        if ($estilos) {
                            $css = implode(' ', array_map(fn ($prop, $valor) => "{$prop}: {$valor};", array_keys($estilos), $estilos));
                            $text = '<span style="'.$this->esc($css).'">'.$text.'</span>';
                        }
                        break;
                }
            }

            $html .= $text;
        }

        return $html;
    }

    /**
     * Campo por completar de las plantillas: su etiqueta entre corchetes, como se ve en el editor. Las variables
     * de una plantilla generada (solo en su documento de trabajo) van entre llaves: «{Fecha}».
     */
    private function textoCampo(array $campo): string
    {
        if (($campo['type'] ?? null) === 'variable') {
            return '{'.(VariablesPlantillaService::VARIABLES[$campo['attrs']['clave'] ?? ''] ?? 'Variable').'}';
        }

        return '['.mb_substr(trim((string) ($campo['attrs']['etiqueta'] ?? '')), 0, 120).']';
    }

    private function esc(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Solo enlaces web, de correo o internos: descarta javascript:, data:, etc. */
    private function hrefSeguro(mixed $href): string
    {
        $href = trim((string) $href);

        return preg_match('#^(https?://|mailto:|\#|/(?!/))#i', $href) ? $href : '#';
    }

    /** Valor CSS que cumple el patrón dado, o null. */
    private function valorCss(mixed $valor, string $patron): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' && preg_match($patron, $valor) ? $valor : null;
    }

    /** Alineación del párrafo o título (solo los valores de TipTap). */
    private function estiloBloque(array $node): string
    {
        $formato = $this->formatoParrafo($node);
        $reglas = array_filter([
            'text-align' => $formato['alineacion'],
            'margin-left' => $formato['sangria'] ? round($formato['sangria'] * self::SANGRIA_PT, 2).'pt' : null,
            'margin-right' => $formato['derecha'] ? $formato['derecha'].'px' : null,
            'text-indent' => $formato['primera'] ? $formato['primera'].'px' : null,
            'line-height' => $formato['interlineado'],
            'margin-top' => $formato['antes'] !== null ? $formato['antes'].'px' : null,
            'margin-bottom' => $formato['despues'] !== null ? $formato['despues'].'px' : null,
        ], fn ($valor) => $valor !== null);

        if ($reglas === []) {
            return '';
        }

        return ' style="'.implode(' ', array_map(fn ($regla, $valor) => "{$regla}: {$valor};", array_keys($reglas), $reglas)).'"';
    }

    /**
     * Formato de párrafo que guarda el editor (alineación, sangría, interlineado y espacio antes/después),
     * validado para que nada distinto a números o valores conocidos llegue al style ni al .docx.
     *
     * La sangría izquierda va en niveles (puede quedar entre niveles si se puso con la regla); la de primera
     * línea (negativa: francesa) y la derecha, en px.
     *
     * @return array{alineacion: ?string, sangria: float, primera: int, derecha: int, interlineado: ?string, antes: ?int, despues: ?int}
     */
    private function formatoParrafo(array $node): array
    {
        $attrs = $node['attrs'] ?? [];
        $alineacion = $attrs['textAlign'] ?? null;
        $interlineado = (string) ($attrs['lineHeight'] ?? '');
        $espacio = fn ($valor) => is_numeric($valor) ? max(0, min((int) $valor, 200)) : null;

        return [
            'alineacion' => in_array($alineacion, ['left', 'center', 'right', 'justify'], true) ? $alineacion : null,
            // Niveles de sangría del editor: 0,5" cada uno, hasta 8
            'sangria' => is_numeric($attrs['indent'] ?? null) ? round(max(0, min((float) $attrs['indent'], 8)), 3) : 0,
            'primera' => is_numeric($attrs['sangriaPrimera'] ?? null) ? (int) max(-384, min((float) $attrs['sangriaPrimera'], 384)) : 0,
            'derecha' => is_numeric($attrs['sangriaDerecha'] ?? null) ? (int) max(0, min((float) $attrs['sangriaDerecha'], 384)) : 0,
            'interlineado' => preg_match('/^\d{1,2}(\.\d{1,2})?$/', $interlineado) ? $interlineado : null,
            'antes' => $espacio($attrs['spaceBefore'] ?? null),
            'despues' => $espacio($attrs['spaceAfter'] ?? null),
        ];
    }

    /** El mismo formato de párrafo en el estilo de PhpWord (twips: 1 pt = 20; 1 px = 15). */
    private function estiloParrafoDocx(array $node): array
    {
        $formato = $this->formatoParrafo($node);

        return array_filter([
            'alignment' => match ($formato['alineacion']) {
                'center' => 'center',
                'right' => 'end',
                'justify' => 'both',
                'left' => 'start',
                default => null,
            },
            'indentation' => array_filter([
                'left' => $formato['sangria'] ? (int) round($formato['sangria'] * self::SANGRIA_PT * 20) : null,
                'right' => $formato['derecha'] ? $formato['derecha'] * 15 : null,
                'firstLine' => $formato['primera'] > 0 ? $formato['primera'] * 15 : null,
                'hanging' => $formato['primera'] < 0 ? -$formato['primera'] * 15 : null,
            ]) ?: null,
            'lineHeight' => $formato['interlineado'] !== null ? (float) $formato['interlineado'] : null,
            'spaceBefore' => $formato['antes'] !== null ? $formato['antes'] * 15 : null,
            'spaceAfter' => $formato['despues'] !== null ? $formato['despues'] * 15 : null,
        ], fn ($valor) => $valor !== null);
    }

    /** Color CSS válido (hex, nombre, rgb/hsl) o null: impide inyectar otras reglas en el style. */
    private function colorCss(mixed $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20}|(rgb|hsl)a?\([\d\s.,%]+\))$/i', $color) ? $color : null;
    }

    private function contenidoToText(array $contenido): string
    {
        $text = '';

        foreach ($this->bloques($contenido) as $block) {
            if (! isset($block['type'])) {
                continue;
            }

            switch ($block['type']) {
                case 'doc':
                    $text .= $this->contenidoToText($block['content'] ?? []);
                    break;
                case 'paragraph':
                    $text .= $this->renderInlineText($block['content'] ?? [])."\n\n";
                    break;
                case 'listItem':
                case 'taskItem':
                    $text .= $this->contenidoToText($block['content'] ?? []);
                    break;
                case 'heading':
                    $text .= mb_strtoupper($this->renderInlineText($block['content'] ?? []))."\n\n";
                    break;
                case 'bulletList':
                    foreach ($block['content'] ?? [] as $item) {
                        $text .= '• '.$this->contenidoToText([$item])."\n";
                    }
                    $text .= "\n";
                    break;
                case 'orderedList':
                    $i = 1;
                    foreach ($block['content'] ?? [] as $item) {
                        $text .= $i.'. '.$this->contenidoToText([$item])."\n";
                        $i++;
                    }
                    $text .= "\n";
                    break;
                case 'taskList':
                    foreach ($block['content'] ?? [] as $item) {
                        $checked = $item['attrs']['checked'] ?? false ? '[X]' : '[ ]';
                        $text .= $checked.' '.$this->contenidoToText([$item])."\n";
                    }
                    $text .= "\n";
                    break;
                case 'blockquote':
                    $text .= '> '.$this->renderInlineText($block['content'] ?? [])."\n\n";
                    break;
                case 'codeBlock':
                    $text .= $this->renderInlineText($block['content'] ?? [])."\n\n";
                    break;
                case 'horizontalRule':
                    $text .= "---\n\n";
                    break;
                case 'table':
                    foreach ($block['content'] ?? [] as $row) {
                        foreach ($row['content'] ?? [] as $cell) {
                            $text .= $this->contenidoToText($cell['content'] ?? [])."\t";
                        }
                        $text .= "\n";
                    }
                    $text .= "\n";
                    break;
            }
        }

        return trim($text);
    }

    private function renderInlineText(array $content): string
    {
        $text = '';

        foreach ($content as $inline) {
            if (in_array($inline['type'] ?? null, ['campo', 'variable'], true)) {
                $text .= $this->textoCampo($inline);
            } elseif (isset($inline['text'])) {
                $text .= $inline['text'];
            }
        }

        return $text;
    }

    private function agregarContenidoASection($section, array $contenido): void
    {
        foreach ($contenido as $block) {
            if (! isset($block['type'])) {
                continue;
            }

            switch ($block['type']) {
                case 'doc':
                    $this->agregarContenidoASection($section, $block['content'] ?? []);
                    break;
                case 'paragraph':
                    // Un tramo por texto para conservar su formato (antes el párrafo iba como texto plano)
                    $this->agregarTramos($section->addTextRun($this->estiloParrafoDocx($block)), $block['content'] ?? []);
                    break;
                case 'heading':
                    $level = $block['attrs']['level'] ?? 1;
                    $section->addTitle($this->renderInlineText($block['content'] ?? []), $level);
                    break;
                case 'bulletList':
                case 'orderedList':
                    $this->agregarLista($section, $block);
                    break;
                case 'blockquote':
                    $section->addText($this->renderInlineText($block['content'] ?? []), ['italic' => true]);
                    break;
                case 'horizontalRule':
                    $section->addHorizontalRule();
                    break;
            }
        }
    }

    /**
     * Lista con numeración de Word: cada lista tiene la suya (empieza en su número) y las anidadas van un
     * nivel más adentro. Las listas anidadas sin estilo dentro de una multinivel comparten su numeración,
     * cuyos niveles muestran los números exteriores (%1.%2.).
     */
    private function agregarLista($section, array $lista, int $nivel = 0, ?string $multinivel = null): void
    {
        $estilo = $this->estiloLista($lista);
        $ordenada = $lista['type'] === 'orderedList';
        if (! ($ordenada && $multinivel !== null && $estilo === null)) {
            $multinivel = $ordenada && $estilo === 'multinivel' ? $this->registrarNumeracion($lista, $nivel) : null;
        }
        $numeracion = $multinivel ?? $this->registrarNumeracion($lista, $nivel);

        foreach ($lista['content'] ?? [] as $item) {
            $primero = true;
            foreach ($item['content'] ?? [] as $hijo) {
                $tipo = $hijo['type'] ?? null;
                if ($tipo === 'bulletList' || $tipo === 'orderedList') {
                    $this->agregarLista($section, $hijo, min($nivel + 1, 8), $multinivel);
                } elseif ($primero && ($tipo === 'paragraph' || $tipo === 'heading')) {
                    $run = $section->addListItemRun($nivel, $numeracion, $this->estiloParrafoDocx($hijo));
                    $this->agregarTramos($run, $hijo['content'] ?? []);
                } else {
                    $this->agregarContenidoASection($section, [$hijo]);
                }
                $primero = false;
            }
        }
    }

    /** Registra la numeración de Word de una lista y devuelve su nombre. */
    private function registrarNumeracion(array $lista, int $nivelLista): string
    {
        $estilo = $this->estiloLista($lista);
        $ordenada = $lista['type'] === 'orderedList';
        $niveles = [];
        for ($nivel = 0; $nivel < 9; $nivel++) {
            $clave = $estilo ?? ($ordenada ? 'decimal' : self::VINETAS_POR_NIVEL[$nivel % 3]);
            [, $formato, $texto] = self::ESTILOS_LISTA[$clave];
            if ($formato !== 'bullet') {
                $texto = $estilo === 'multinivel'
                    ? implode('.', array_map(fn ($n) => '%'.($n + 1), range(min($nivelLista, $nivel), $nivel))).'.'
                    : str_replace('%', '%'.($nivel + 1), $texto);
            }
            $niveles[] = [
                'format' => $formato,
                'text' => $texto,
                'start' => $nivel === $nivelLista ? $this->inicioLista($lista) : 1,
                'left' => 720 * ($nivel + 1),
                'hanging' => 360,
                'tabPos' => 720 * ($nivel + 1),
            ];
        }
        $nombre = 'ListaDocumento'.(++$this->listasDocx);
        $this->phpWord->addNumberingStyle($nombre, ['type' => 'multilevel', 'levels' => $niveles]);

        return $nombre;
    }

    /** Textos y saltos de línea de un párrafo, cada uno con el estilo de sus marcas. */
    private function agregarTramos($run, array $contenido): void
    {
        foreach ($contenido as $inline) {
            if (($inline['type'] ?? null) === 'hardBreak') {
                $run->addTextBreak();
            } elseif (in_array($inline['type'] ?? null, ['campo', 'variable'], true)) {
                // Resaltado, para que en Word se vea qué falta por completar
                $run->addText($this->textoCampo($inline), ['fgColor' => 'yellow'] + $this->getTextStyle($inline['marks'] ?? []));
            } elseif (isset($inline['text']) && $inline['text'] !== '') {
                $run->addText($inline['text'], $this->getTextStyle($inline['marks'] ?? []));
            }
        }
    }

    private function getTextStyle(array $marks): array
    {
        $style = [];

        foreach ($marks as $mark) {
            switch ($mark['type']) {
                case 'bold':
                    $style['bold'] = true;
                    break;
                case 'italic':
                    $style['italic'] = true;
                    break;
                case 'underline':
                    $style['underline'] = 'single';
                    break;
                case 'strike':
                    $style['strikethrough'] = true;
                    break;
                case 'code':
                    $style['code'] = true;
                    break;
                case 'highlight':
                    // Word solo admite sus colores de resaltado con nombre
                    $style['fgColor'] = 'yellow';
                    break;
                case 'insercion':
                    $style['underline'] = 'single';
                    $style['color'] = '2E7D32';
                    break;
                case 'eliminacion':
                    $style['strikethrough'] = true;
                    $style['color'] = 'C62828';
                    break;
                case 'textStyle':
                    $color = ltrim((string) ($mark['attrs']['color'] ?? ''), '#');
                    if (preg_match('/^[0-9a-f]{6}$/i', $color)) {
                        $style['color'] = strtoupper($color);
                    }
                    break;
            }
        }

        return $style;
    }

    private function wrapHtmlDocument(string $titulo, string $contenido): string
    {
        $tituloHtml = $this->esc($titulo);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$tituloHtml}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: Arial, sans-serif;
            margin-top: 24px;
            margin-bottom: 12px;
        }
        h1 { font-size: 24pt; }
        h2 { font-size: 18pt; }
        h3 { font-size: 14pt; }
        p { margin-bottom: 12px; text-align: justify; }
        blockquote {
            border-left: 3px solid #ccc;
            padding-left: 15px;
            margin-left: 0;
            font-style: italic;
        }
        pre, code {
            background-color: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            padding: 10px;
            overflow-x: auto;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        ul, ol {
            margin-left: 20px;
        }
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <h1>{$tituloHtml}</h1>
    {$contenido}
</body>
</html>
HTML;
    }
}
