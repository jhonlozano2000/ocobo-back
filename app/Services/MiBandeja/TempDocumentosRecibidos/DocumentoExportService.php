<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocumentoExportService
{
    /** Marcador del total de hojas en el pie del PDF; se reemplaza tras contar las hojas. */
    private const TOTAL_PAGINAS = '__TOTAL_PAGINAS__';

    /** Un nivel de sangría del editor: 0,5" = 36 pt. */
    private const SANGRIA_PT = 36;

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

        $headerPartes = $this->partesFranja($config['header'] ?? null, $titulo, $autor, true);
        $footerPartes = $this->partesFranja($config['footer'] ?? null, $titulo, $autor, true);

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
     * @param  bool  $html  true: texto escapado para el PDF; false: texto plano para PhpWord
     * @return string[]
     */
    private function partesFranja(?array $franja, string $titulo, string $autor, bool $html): array
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
            } elseif ($valor !== '') {
                // En el .docx las llaves del texto se tomarían por campos de Word
                $partes[] = $html ? $this->esc($valor) : str_replace(['{', '}'], ['(', ')'], $valor);
            }
        }

        return $partes;
    }

    private function nombreCreador(Documento $documento): string
    {
        $creador = $documento->creador;

        return $creador ? (trim($creador->nombres.' '.$creador->apellidos) ?: (string) $creador->name) : '';
    }

    public function exportarDocx(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $config = $documento->getConfiguracionPagina();
        $phpWord = new PhpWord;

        $section = $phpWord->addSection();
        foreach (['header' => 'addHeader', 'footer' => 'addFooter'] as $clave => $metodo) {
            $partes = $this->partesFranja($config[$clave] ?? null, (string) $documento->titulo, $this->nombreCreador($documento), false);
            if ($partes) {
                $section->{$metodo}()->addPreserveText(implode('  |  ', $partes), ['size' => 9, 'color' => '666666'], ['alignment' => 'center']);
            }
        }
        $this->agregarContenidoASection($section, $this->bloques($contenido->contenido_yjs ?? []));

        $filename = 'documento_'.$documento->id.'_'.time().'.docx';
        $path = 'exports/'.$filename;

        Storage::disk('local')->makeDirectory('exports');

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save(Storage::disk('local')->path($path));

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
                return '<p'.$this->estiloBloque($node).'>'.$this->renderInlineContent($node['content'] ?? []).'</p>';
            case 'heading':
                $level = min(max((int) ($node['attrs']['level'] ?? 1), 1), 6);

                return "<h{$level}".$this->estiloBloque($node).'>'.$this->renderInlineContent($node['content'] ?? [])."</h{$level}>";
            case 'bulletList':
                return '<ul>'.$hijos().'</ul>';
            case 'orderedList':
                return '<ol>'.$hijos().'</ol>';
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

            $text = $this->esc($inline['text'] ?? '');

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
            'margin-left' => $formato['sangria'] ? ($formato['sangria'] * self::SANGRIA_PT).'pt' : null,
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
     * @return array{alineacion: ?string, sangria: int, interlineado: ?string, antes: ?int, despues: ?int}
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
            'sangria' => max(0, min((int) ($attrs['indent'] ?? 0), 8)),
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
            'indentation' => $formato['sangria'] ? ['left' => $formato['sangria'] * self::SANGRIA_PT * 20] : null,
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
            if (isset($inline['text'])) {
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
                    $section->addText(
                        $this->renderInlineText($block['content'] ?? []),
                        $this->getTextStyle($block['marks'] ?? []),
                        $this->estiloParrafoDocx($block)
                    );
                    break;
                case 'heading':
                    $level = $block['attrs']['level'] ?? 1;
                    $section->addTitle($this->renderInlineText($block['content'] ?? []), $level);
                    break;
                case 'bulletList':
                case 'orderedList':
                    foreach ($block['content'] ?? [] as $item) {
                        $text = $this->renderInlineText($item['content'][0]['content'] ?? []);
                        if ($block['type'] === 'bulletList') {
                            $section->addListItem($text);
                        } else {
                            $section->addListItem($text, 0, null, 'ordered');
                        }
                    }
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
                    $style['underline'] = true;
                    break;
                case 'strike':
                    $style['strikethrough'] = true;
                    break;
                case 'code':
                    $style['code'] = true;
                    break;
                case 'highlight':
                    $color = $mark['attrs']['color'] ?? 'yellow';
                    $style['highlight'] = $color;
                    break;
                case 'textStyle':
                    $color = $mark['attrs']['color'] ?? '';
                    if ($color) {
                        $style['color'] = $color;
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
