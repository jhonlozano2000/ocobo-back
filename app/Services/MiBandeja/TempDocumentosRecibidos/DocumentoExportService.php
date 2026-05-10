<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DocumentoExportService
{
    public function exportarPdf(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $configPagina = $documento->getConfiguracionPagina();
        $creador = $documento->creador;
        $creadorNombre = $creador ? trim($creador->nombres . ' ' . $creador->apellidos) ?: $creador->name : '';

        $html = $this->contenidoToHtml($contenido->contenido_yjs ?? []);
        $contenidoHtml = $this->wrapHtmlForPdf(
            $documento->titulo,
            $html,
            $configPagina,
            $creadorNombre
        );

        $pdf = Pdf::loadHTML($contenidoHtml);

        $papel = match($configPagina['tamano_papel']) {
            'carta' => 'letter',
            'legal' => 'legal',
            'oficio' => [0, 0, 215.9, 330.2],
            default => 'a4',
        };

        $orientacion = $configPagina['orientacion'] === 'horizontal' ? 'landscape' : 'portrait';
        $pdf->setPaper($papel, $orientacion);

        $margenes = $configPagina['margenes'];
        $pdf->setOptions([
            'dpi' => 96,
            'defaultFont' => 'sans-serif',
        ]);

        $filename = 'documento_' . $documento->id . '_' . time() . '.pdf';
        $path = 'exports/' . $filename;

        Storage::disk('local')->makeDirectory('exports');
        Storage::disk('local')->put($path, $pdf->output());

        return Storage::disk('local')->path($path);
    }

    private function wrapHtmlForPdf(string $titulo, string $contenido, array $config, string $autor): string
    {
        $margenes = $config['margenes'] ?? ['superior' => 25.4, 'inferior' => 25.4, 'izquierdo' => 25.4, 'derecho' => 25.4];
        $mmToPt = 2.83465;

        $marginTop = round($margenes['superior'] * $mmToPt);
        $marginBottom = round($margenes['inferior'] * $mmToPt);
        $marginLeft = round($margenes['izquierdo'] * $mmToPt);
        $marginRight = round($margenes['derecho'] * $mmToPt);

        $headerHtml = '';
        $footerHtml = '';

        if (!empty($config['header']['habilitado'])) {
            $headerElementos = $config['header']['elementos'] ?? [];
            $headerParts = [];

            foreach ($headerElementos as $elem) {
                $val = match($elem) {
                    'titulo' => htmlspecialchars($titulo),
                    'autor' => htmlspecialchars($autor),
                    'fecha' => date('d/m/Y'),
                    'texto_libre' => htmlspecialchars($config['header']['texto_libre'] ?? ''),
                    default => '',
                };
                if ($val) $headerParts[] = $val;
            }

            if (!empty($headerParts)) {
                $headerHtml = '<div style="text-align: center; font-size: 9pt; color: #666; border-bottom: 0.5px solid #ccc; padding-bottom: 8pt; margin-bottom: 8pt; width: 100%;">' . implode(' &nbsp;|&nbsp; ', $headerParts) . '</div>';
            }
        }

        if (!empty($config['footer']['habilitado'])) {
            $footerElementos = $config['footer']['elementos'] ?? [];
            $footerParts = [];

            foreach ($footerElementos as $elem) {
                $val = match($elem) {
                    'titulo' => htmlspecialchars($titulo),
                    'autor' => htmlspecialchars($autor),
                    'fecha' => date('d/m/Y'),
                    'texto_libre' => htmlspecialchars($config['footer']['texto_libre'] ?? ''),
                    default => '',
                };
                if ($val) $footerParts[] = $val;
            }

            if (!empty($footerParts)) {
                $footerHtml = '<div style="text-align: center; font-size: 9pt; color: #666; border-top: 0.5px solid #ccc; padding-top: 8pt; margin-top: 8pt; width: 100%;">' . implode(' &nbsp;|&nbsp; ', $footerParts) . '</div>';
            }
        }

        $columns = $config['columnas'] ?? ['numero' => 1];
        $columnCss = '';
        if (($columns['numero'] ?? 1) > 1) {
            $columnCount = $columns['numero'];
            $columnRule = !empty($columns['con_linea']) ? '1px solid #ccc' : 'none';
            $columnCss = "-webkit-column-count: {$columnCount}; -moz-column-count: {$columnCount}; column-count: {$columnCount}; -webkit-column-rule: {$columnRule}; -moz-column-rule: {$columnRule}; column-rule: {$columnRule}; -webkit-column-gap: 32px; -moz-column-gap: 32px; column-gap: 32px;";
        }

        $headerBlock = $headerHtml ? "<div style='margin-bottom: 16pt;'>{$headerHtml}</div>" : '';
        $footerBlock = $footerHtml ? "<div style='margin-top: 16pt;'>{$footerHtml}</div>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$titulo}</title>
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
    </style>
</head>
<body>
    {$headerBlock}
    <div class="contenido-pagina">
        {$contenido}
    </div>
    {$footerBlock}
</body>
</html>
HTML;
    }

    public function exportarDocx(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $phpWord = new PhpWord();

        $section = $phpWord->addSection();
        $this->agregarContenidoASection($section, $contenido->contenido_yjs ?? []);

        $filename = 'documento_' . $documento->id . '_' . time() . '.docx';
        $path = 'exports/' . $filename;

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

        $filename = 'documento_' . $documento->id . '_' . time() . '.html';
        $path = 'exports/' . $filename;

        Storage::disk('local')->put($path, $fullHtml);

        return Storage::disk('local')->path($path);
    }

    public function exportarTxt(Documento $documento): string
    {
        $contenido = $documento->contenido;
        $texto = $this->contenidoToText($contenido->contenido_yjs ?? []);

        $filename = 'documento_' . $documento->id . '_' . time() . '.txt';
        $path = 'exports/' . $filename;

        Storage::disk('local')->put($path, $texto);

        return Storage::disk('local')->path($path);
    }

    private function contenidoToHtml(array $contenido): string
    {
        if (empty($contenido)) {
            return '<p></p>';
        }

        $html = '';

        foreach ($contenido as $block) {
            if (!isset($block['type'])) continue;

            switch ($block['type']) {
                case 'doc':
                    $html .= $this->renderNode($block);
                    break;
                case 'paragraph':
                    $html .= '<p>' . $this->renderInlineContent($block['content'] ?? []) . '</p>';
                    break;
                case 'heading':
                    $level = $block['attrs']['level'] ?? 1;
                    $html .= "<h{$level}>" . $this->renderInlineContent($block['content'] ?? []) . "</h{$level}>";
                    break;
                case 'bulletList':
                case 'orderedList':
                    $tag = $block['type'] === 'bulletList' ? 'ul' : 'ol';
                    $html .= "<{$tag}>";
                    foreach ($block['content'] ?? [] as $item) {
                        $html .= '<li>' . $this->renderNode($item) . '</li>';
                    }
                    $html .= "</{$tag}>";
                    break;
                case 'taskList':
                    $html .= '<ul>';
                    foreach ($block['content'] ?? [] as $item) {
                        $checked = $item['attrs']['checked'] ?? false ? 'checked' : '';
                        $html .= '<li><input type="checkbox" ' . $checked . ' disabled> ' . $this->renderNode($item) . '</li>';
                    }
                    $html .= '</ul>';
                    break;
                case 'blockquote':
                    $html .= '<blockquote>' . $this->renderInlineContent($block['content'] ?? []) . '</blockquote>';
                    break;
                case 'codeBlock':
                    $code = $this->renderInlineContent($block['content'] ?? []);
                    $html .= '<pre><code>' . $code . '</code></pre>';
                    break;
                case 'horizontalRule':
                    $html .= '<hr>';
                    break;
                case 'table':
                    $html .= '<table border="1" cellpadding="5" cellspacing="0">';
                    foreach ($block['content'] ?? [] as $row) {
                        $isHeader = ($row['type'] ?? '') === 'tableRow' && isset($row['content'][0]['type']) && ($row['content'][0]['type'] ?? '') === 'tableHeader';
                        $tag = $isHeader ? 'th' : 'td';
                        $html .= '<tr>';
                        foreach ($row['content'] ?? [] as $cell) {
                            $html .= '<' . $tag . '>' . $this->renderNode($cell) . '</' . $tag . '>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</table>';
                    break;
                case 'image':
                    $src = $block['attrs']['src'] ?? '';
                    $alt = $block['attrs']['alt'] ?? '';
                    $html .= '<img src="' . $src . '" alt="' . $alt . '" style="max-width: 100%;">';
                    break;
            }
        }

        return $html;
    }

    private function renderNode($node): string
    {
        if (!isset($node['type'])) return '';

        switch ($node['type']) {
            case 'paragraph':
                return '<p>' . $this->renderInlineContent($node['content'] ?? []) . '</p>';
            case 'heading':
                $level = $node['attrs']['level'] ?? 1;
                return "<h{$level}>" . $this->renderInlineContent($node['content'] ?? []) . "</h{$level}>";
            case 'listItem':
            case 'taskItem':
                return $this->renderInlineContent($node['content'] ?? []);
            case 'tableCell':
            case 'tableHeader':
                return $this->renderInlineContent($node['content'] ?? []);
            default:
                return $this->renderInlineContent($node['content'] ?? []);
        }
    }

    private function renderInlineContent(array $content): string
    {
        if (empty($content)) return '';

        $html = '';

        foreach ($content as $inline) {
            if (!isset($inline['type'])) continue;

            $text = $inline['text'] ?? '';
            $marks = $inline['marks'] ?? [];

            foreach ($marks as $mark) {
                switch ($mark['type']) {
                    case 'bold':
                        $text = '<strong>' . $text . '</strong>';
                        break;
                    case 'italic':
                        $text = '<em>' . $text . '</em>';
                        break;
                    case 'underline':
                        $text = '<u>' . $text . '</u>';
                        break;
                    case 'strike':
                        $text = '<s>' . $text . '</s>';
                        break;
                    case 'code':
                        $text = '<code>' . $text . '</code>';
                        break;
                    case 'highlight':
                        $color = $mark['attrs']['color'] ?? 'yellow';
                        $text = '<span style="background-color: ' . $color . ';">' . $text . '</span>';
                        break;
                    case 'link':
                        $href = $mark['attrs']['href'] ?? '#';
                        $text = '<a href="' . $href . '">' . $text . '</a>';
                        break;
                    case 'textStyle':
                        $color = $mark['attrs']['color'] ?? '';
                        if ($color) {
                            $text = '<span style="color: ' . $color . ';">' . $text . '</span>';
                        }
                        break;
                }
            }

            $html .= $text;
        }

        return $html;
    }

    private function contenidoToText(array $contenido): string
    {
        $text = '';

        foreach ($contenido as $block) {
            if (!isset($block['type'])) continue;

            switch ($block['type']) {
                case 'doc':
                    $text .= $this->contenidoToText($block['content'] ?? []);
                    break;
                case 'paragraph':
                    $text .= $this->renderInlineText($block['content'] ?? []) . "\n\n";
                    break;
                case 'heading':
                    $text .= strtoupper($this->renderInlineText($block['content'] ?? [])) . "\n\n";
                    break;
                case 'bulletList':
                    foreach ($block['content'] ?? [] as $item) {
                        $text .= "• " . $this->contenidoToText([$item]) . "\n";
                    }
                    $text .= "\n";
                    break;
                case 'orderedList':
                    $i = 1;
                    foreach ($block['content'] ?? [] as $item) {
                        $text .= $i . ". " . $this->contenidoToText([$item]) . "\n";
                        $i++;
                    }
                    $text .= "\n";
                    break;
                case 'taskList':
                    foreach ($block['content'] ?? [] as $item) {
                        $checked = $item['attrs']['checked'] ?? false ? '[X]' : '[ ]';
                        $text .= $checked . " " . $this->contenidoToText([$item]) . "\n";
                    }
                    $text .= "\n";
                    break;
                case 'blockquote':
                    $text .= "> " . $this->renderInlineText($block['content'] ?? []) . "\n\n";
                    break;
                case 'codeBlock':
                    $text .= $this->renderInlineText($block['content'] ?? []) . "\n\n";
                    break;
                case 'horizontalRule':
                    $text .= "---\n\n";
                    break;
                case 'table':
                    foreach ($block['content'] ?? [] as $row) {
                        foreach ($row['content'] ?? [] as $cell) {
                            $text .= $this->renderInlineText($cell['content'] ?? []) . "\t";
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
            if (!isset($block['type'])) continue;

            switch ($block['type']) {
                case 'doc':
                    $this->agregarContenidoASection($section, $block['content'] ?? []);
                    break;
                case 'paragraph':
                    $section->addText(
                        $this->renderInlineText($block['content'] ?? []),
                        $this->getTextStyle($block['marks'] ?? [])
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
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo}</title>
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
    <h1>{$titulo}</h1>
    {$contenido}
</body>
</html>
HTML;
    }
}