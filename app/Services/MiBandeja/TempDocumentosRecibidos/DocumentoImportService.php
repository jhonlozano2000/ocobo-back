<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Illuminate\Http\UploadedFile;

class DocumentoImportService
{
    public function importar(UploadedFile $archivo, int $userId, ?int $radicaReciId = null): Documento
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        $contenido = match($extension) {
            'docx' => $this->importarDocx($archivo),
            'doc' => $this->importarDocx($archivo),
            'html', 'htm' => $this->importarHtml($archivo),
            'txt' => $this->importarTxt($archivo),
            default => throw new \InvalidArgumentException("Formato de archivo no soportado: {$extension}")
        };

        $documento = Documento::create([
            'user_id' => $userId,
            'radica_reci_id' => $radicaReciId,
            'titulo' => pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME),
            'estado' => 'borrador',
            'nombre_archivo_original' => $archivo->getClientOriginalName(),
            'archivo_path' => $archivo->store('imports'),
        ]);

        Contenido::create([
            'documento_id' => $documento->id,
            'contenido_yjs' => $contenido,
            'hash_contenido' => hash('sha256', json_encode($contenido)),
            'actualizado_por' => $userId,
        ]);

        return $documento;
    }

    public function importarDesdeTexto(string $html, string $titulo, int $userId, ?int $radicaReciId = null): Documento
    {
        $contenido = $this->htmlToTipTap($html);

        $documento = Documento::create([
            'user_id' => $userId,
            'radica_reci_id' => $radicaReciId,
            'titulo' => $titulo,
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $documento->id,
            'contenido_yjs' => $contenido,
            'hash_contenido' => hash('sha256', json_encode($contenido)),
            'actualizado_por' => $userId,
        ]);

        return $documento;
    }

    private function importarDocx(UploadedFile $archivo): array
    {
        $tempPath = $archivo->getRealPath();
        
        $zip = new \ZipArchive();
        if ($zip->open($tempPath) === true) {
            $documentXml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($documentXml) {
                return $this->xmlToTipTap($documentXml);
            }
        }

        $phpWord = \PhpOffice\PhpWord\IOFactory::load($tempPath);
        return $this->phpWordToTipTap($phpWord);
    }

    private function importarHtml(UploadedFile $archivo): array
    {
        $html = file_get_contents($archivo->getRealPath());
        return $this->htmlToTipTap($html);
    }

    private function importarTxt(UploadedFile $archivo): array
    {
        $texto = file_get_contents($archivo->getRealPath());
        $lineas = explode("\n", $texto);

        $contenido = [
            'type' => 'doc',
            'content' => []
        ];

        foreach ($lineas as $linea) {
            if (trim($linea) === '') continue;
            
            $contenido['content'][] = [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => trim($linea)]
                ]
            ];
        }

        return $contenido;
    }

    private function xmlToTipTap(string $xml): array
    {
        $contenido = [
            'type' => 'doc',
            'content' => []
        ];

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $node) {
                $parsed = $this->parseXmlNode($node);
                if ($parsed) {
                    $contenido['content'][] = $parsed;
                }
            }
        }

        return $contenido;
    }

    private function parseXmlNode(\DOMNode $node): ?array
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }

        $localName = $node->localName ?? $node->nodeName;

        return match($localName) {
            'p' => $this->parseParagraph($node),
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->parseHeading($node, (int) substr($localName, 1)),
            'ul' => $this->parseList($node, 'bulletList'),
            'ol' => $this->parseList($node, 'orderedList'),
            'tbl' => $this->parseTable($node),
            'blockquote' => $this->parseBlockquote($node),
            'pre' => $this->parseCodeBlock($node),
            'hr' => ['type' => 'horizontalRule'],
            default => $this->parseParagraph($node),
        };
    }

    private function parseParagraph(\DOMNode $node): array
    {
        $content = $this->parseInlineElements($node);
        
        return [
            'type' => 'paragraph',
            'content' => $content ?: [['type' => 'text', 'text' => '']]
        ];
    }

    private function parseHeading(\DOMNode $node, int $level): array
    {
        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => $this->parseInlineElements($node)
        ];
    }

    private function parseList(\DOMNode $node, string $type): array
    {
        $items = [];
        
        foreach ($node->childNodes as $child) {
            if ($child->localName === 'li' || $child->nodeName === 'li') {
                $items[] = [
                    'type' => 'listItem',
                    'content' => [$this->parseParagraph($child)]
                ];
            }
        }

        return [
            'type' => $type,
            'content' => $items
        ];
    }

    private function parseBlockquote(\DOMNode $node): array
    {
        return [
            'type' => 'blockquote',
            'content' => $this->parseInlineElements($node)
        ];
    }

    private function parseCodeBlock(\DOMNode $node): array
    {
        return [
            'type' => 'codeBlock',
            'content' => [
                ['type' => 'text', 'text' => trim($node->textContent)]
            ]
        ];
    }

    private function parseTable(\DOMNode $node): array
    {
        $rows = [];

        foreach ($node->childNodes as $rowNode) {
            if ($rowNode->localName !== 'tr' && $rowNode->nodeName !== 'tr') continue;

            $cells = [];
            foreach ($rowNode->childNodes as $cellNode) {
                $cellType = $cellNode->localName === 'th' || $cellNode->nodeName === 'th' ? 'tableHeader' : 'tableCell';
                $cells[] = [
                    'type' => $cellType,
                    'content' => [$this->parseParagraph($cellNode)]
                ];
            }

            $rows[] = [
                'type' => 'tableRow',
                'content' => $cells
            ];
        }

        return [
            'type' => 'table',
            'content' => $rows
        ];
    }

    private function parseInlineElements(\DOMNode $node): array
    {
        $content = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $content[] = ['type' => 'text', 'text' => $text];
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $marks = [];
                $localName = $child->localName ?? $child->nodeName;

                $markType = match($localName) {
                    'b', 'strong' => 'bold',
                    'i', 'em' => 'italic',
                    'u' => 'underline',
                    's', 'strike', 'del' => 'strike',
                    'code' => 'code',
                    'a' => 'link',
                    'span' => $this->getSpanMark($child),
                    default => null
                };

                if ($markType) {
                    if (is_array($markType)) {
                        $marks[] = $markType;
                    } elseif ($markType === 'link') {
                        $href = $child->getAttribute('href') ?? '#';
                        $marks[] = ['type' => 'link', 'attrs' => ['href' => $href]];
                    } elseif ($markType) {
                        $marks[] = ['type' => $markType];
                    }
                }

                $text = trim($child->textContent);
                if ($text !== '') {
                    $inlineContent = ['type' => 'text', 'text' => $text];
                    if (!empty($marks)) {
                        $inlineContent['marks'] = $marks;
                    }
                    $content[] = $inlineContent;
                }
            }
        }

        return $content;
    }

    private function getSpanMark(\DOMNode $node): ?array
    {
        $style = $node->getAttribute('style') ?? '';
        
        if (preg_match('/color:\s*([^;]+)/', $style, $matches)) {
            return ['type' => 'textStyle', 'attrs' => ['color' => trim($matches[1])]];
        }
        
        return null;
    }

    private function phpWordToTipTap($phpWord): array
    {
        $contenido = [
            'type' => 'doc',
            'content' => []
        ];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $parsed = $this->parsePhpWordElement($element);
                if ($parsed) {
                    $contenido['content'][] = $parsed;
                }
            }
        }

        return $contenido;
    }

    private function parsePhpWordElement($element): ?array
    {
        $className = get_class($element);

        return match(true) {
            str_contains($className, 'TextRun') => $this->parseTextRun($element),
            str_contains($className, 'Title') => [
                'type' => 'heading',
                'attrs' => ['level' => 1],
                'content' => [['type' => 'text', 'text' => $this->getElementText($element)]]
            ],
            str_contains($className, 'Heading') => [
                'type' => 'heading',
                'attrs' => ['level' => $element->getDepth() ?? 1],
                'content' => [['type' => 'text', 'text' => $this->getElementText($element)]]
            ],
            str_contains($className, 'ListItem') => [
                'type' => 'listItem',
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $this->getElementText($element)]]]]
            ],
            str_contains($className, 'HorizontalRule') => ['type' => 'horizontalRule'],
            default => null
        };
    }

    private function parseTextRun($element): array
    {
        return [
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $this->getElementText($element)]]
        ];
    }

    private function getElementText($element): string
    {
        if (method_exists($element, 'getText')) {
            return $element->getText();
        }
        return '';
    }

    private function htmlToTipTap(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $body = $dom->getElementsByTagName('body')->item(0);
        
        $contenido = [
            'type' => 'doc',
            'content' => []
        ];

        if ($body) {
            foreach ($body->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE) {
                    $parsed = $this->htmlNodeToTipTap($node);
                    if ($parsed) {
                        $contenido['content'][] = $parsed;
                    }
                }
            }
        }

        return $contenido;
    }

    private function htmlNodeToTipTap(\DOMNode $node): ?array
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }

        $localName = strtolower($node->localName ?? $node->nodeName);

        return match($localName) {
            'p', 'div' => $this->htmlParagraphToTipTap($node),
            'h1' => $this->htmlHeadingToTipTap($node, 1),
            'h2' => $this->htmlHeadingToTipTap($node, 2),
            'h3' => $this->htmlHeadingToTipTap($node, 3),
            'h4' => $this->htmlHeadingToTipTap($node, 4),
            'h5' => $this->htmlHeadingToTipTap($node, 5),
            'h6' => $this->htmlHeadingToTipTap($node, 6),
            'ul' => $this->htmlListToTipTap($node, 'bulletList'),
            'ol' => $this->htmlListToTipTap($node, 'orderedList'),
            'table' => $this->htmlTableToTipTap($node),
            'blockquote' => $this->htmlBlockquoteToTipTap($node),
            'pre' => $this->htmlCodeBlockToTipTap($node),
            'hr' => ['type' => 'horizontalRule'],
            'br' => null,
            default => $this->htmlParagraphToTipTap($node)
        };
    }

    private function htmlParagraphToTipTap(\DOMNode $node): array
    {
        return [
            'type' => 'paragraph',
            'content' => $this->htmlInlineToTipTap($node)
        ];
    }

    private function htmlHeadingToTipTap(\DOMNode $node, int $level): array
    {
        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => $this->htmlInlineToTipTap($node)
        ];
    }

    private function htmlListToTipTap(\DOMNode $node, string $type): array
    {
        $items = [];
        
        foreach ($node->childNodes as $child) {
            if (strtolower($child->localName ?? $child->nodeName) === 'li') {
                $items[] = [
                    'type' => 'listItem',
                    'content' => [$this->htmlParagraphToTipTap($child)]
                ];
            }
        }

        return [
            'type' => $type,
            'content' => $items
        ];
    }

    private function htmlTableToTipTap(\DOMNode $node): array
    {
        $rows = [];

        foreach ($node->childNodes as $rowNode) {
            if (strtolower($rowNode->localName ?? $rowNode->nodeName) !== 'tr') continue;

            $cells = [];
            foreach ($rowNode->childNodes as $cellNode) {
                $cellType = strtolower($cellNode->localName ?? $cellNode->nodeName) === 'th' ? 'tableHeader' : 'tableCell';
                $cells[] = [
                    'type' => $cellType,
                    'content' => [$this->htmlParagraphToTipTap($cellNode)]
                ];
            }

            $rows[] = [
                'type' => 'tableRow',
                'content' => $cells
            ];
        }

        return [
            'type' => 'table',
            'content' => $rows
        ];
    }

    private function htmlBlockquoteToTipTap(\DOMNode $node): array
    {
        return [
            'type' => 'blockquote',
            'content' => $this->htmlInlineToTipTap($node)
        ];
    }

    private function htmlCodeBlockToTipTap(\DOMNode $node): array
    {
        return [
            'type' => 'codeBlock',
            'content' => [['type' => 'text', 'text' => trim($node->textContent)]]
        ];
    }

    private function htmlInlineToTipTap(\DOMNode $node): array
    {
        $content = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $content[] = ['type' => 'text', 'text' => $text];
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $localName = strtolower($child->localName ?? $child->nodeName);
                $marks = [];

                switch ($localName) {
                    case 'strong':
                    case 'b':
                        $marks[] = ['type' => 'bold'];
                        break;
                    case 'em':
                    case 'i':
                        $marks[] = ['type' => 'italic'];
                        break;
                    case 'u':
                        $marks[] = ['type' => 'underline'];
                        break;
                    case 's':
                    case 'strike':
                    case 'del':
                        $marks[] = ['type' => 'strike'];
                        break;
                    case 'code':
                        $marks[] = ['type' => 'code'];
                        break;
                    case 'a':
                        $href = $child->getAttribute('href') ?? '#';
                        $marks[] = ['type' => 'link', 'attrs' => ['href' => $href]];
                        break;
                    case 'br':
                        $content[] = ['type' => 'hardBreak'];
                        continue 2;
                }

                $text = trim($child->textContent);
                if ($text !== '') {
                    $inlineContent = ['type' => 'text', 'text' => $text];
                    if (!empty($marks)) {
                        $inlineContent['marks'] = $marks;
                    }
                    $content[] = $inlineContent;
                }
            }
        }

        if (empty($content)) {
            $content[] = ['type' => 'text', 'text' => ''];
        }

        return $content;
    }
}