<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use PhpOffice\PhpWord\PhpWord;

/**
 * Estilos modificados del documento («Modificar estilo» de Word): Normal, Título 1-3 y Cita, guardados en
 * configuracion_formato.estilos con solo lo que el usuario cambió. Los pasa al PDF (CSS) y al .docx (estilos de
 * PhpWord) sobre el aspecto por defecto.
 */
class EstilosDocumento
{
    public const ESTILOS = ['normal', 'titulo_1', 'titulo_2', 'titulo_3', 'cita'];

    public const PROPIEDADES = ['fuente', 'tamano', 'color', 'negrita', 'cursiva', 'alineacion', 'espacio_antes', 'espacio_despues'];

    public const ALINEACIONES = ['left', 'center', 'right', 'justify'];

    /** Fuente: letras, números, espacios y . _ - (nada que cierre la regla CSS). */
    public const PATRON_FUENTE = '/^[\pL\pN ._-]{1,60}$/u';

    public const PATRON_COLOR = '/^#[0-9a-fA-F]{6}$/';

    private const SELECTORES = ['normal' => 'p', 'titulo_1' => 'h1', 'titulo_2' => 'h2', 'titulo_3' => 'h3', 'cita' => 'blockquote'];

    /** Familia genérica del PDF: dompdf solo trae Helvetica, Times y Courier (y DejaVu). */
    private const FUENTES_SERIF = ['times new roman', 'georgia', 'garamond', 'cambria', 'book antiqua'];

    /** Tamaño de los títulos en el .docx si no se cambió (los de Word). */
    private const TAMANO_TITULO = [1 => 16, 2 => 13, 3 => 12];

    /**
     * Estilos válidos, solo con lo que cambió.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function limpiar(?array $estilos): array
    {
        $limpios = [];
        foreach (self::ESTILOS as $clave) {
            $estilo = $estilos[$clave] ?? null;
            if (! is_array($estilo)) {
                continue;
            }
            $numero = fn ($valor, $min, $max) => is_numeric($valor) ? max($min, min((float) $valor, $max)) : null;
            $valores = array_filter([
                'fuente' => is_string($estilo['fuente'] ?? null) && preg_match(self::PATRON_FUENTE, $estilo['fuente']) ? $estilo['fuente'] : null,
                'tamano' => $numero($estilo['tamano'] ?? null, 6, 96),
                'color' => is_string($estilo['color'] ?? null) && preg_match(self::PATRON_COLOR, $estilo['color']) ? $estilo['color'] : null,
                'negrita' => is_bool($estilo['negrita'] ?? null) ? $estilo['negrita'] : null,
                'cursiva' => is_bool($estilo['cursiva'] ?? null) ? $estilo['cursiva'] : null,
                'alineacion' => in_array($estilo['alineacion'] ?? null, self::ALINEACIONES, true) ? $estilo['alineacion'] : null,
                'espacio_antes' => $numero($estilo['espacio_antes'] ?? null, 0, 100),
                'espacio_despues' => $numero($estilo['espacio_despues'] ?? null, 0, 100),
            ], fn ($valor) => $valor !== null);
            if ($valores) {
                $limpios[$clave] = $valores;
            }
        }

        return $limpios;
    }

    /** Reglas CSS para el PDF; van después de las por defecto. Normal da la letra a todo el documento. */
    public static function css(?array $estilos): string
    {
        $css = '';
        foreach (self::limpiar($estilos) as $clave => $estilo) {
            $letra = self::reglasLetra($estilo);
            $parrafo = self::reglasParrafo($estilo);
            if ($clave === 'normal') {
                $css .= $letra !== '' ? "body { {$letra}} " : '';
                $css .= $parrafo !== '' ? "p { {$parrafo}} " : '';
            } elseif ($letra.$parrafo !== '') {
                $css .= self::SELECTORES[$clave]." { {$letra}{$parrafo}} ";
            }
        }

        return $css;
    }

    private static function reglasLetra(array $estilo): string
    {
        return implode('', array_filter([
            isset($estilo['fuente']) ? "font-family: '{$estilo['fuente']}', ".self::generica($estilo['fuente']).'; ' : null,
            isset($estilo['tamano']) ? "font-size: {$estilo['tamano']}pt; " : null,
            isset($estilo['color']) ? "color: {$estilo['color']}; " : null,
        ]));
    }

    private static function reglasParrafo(array $estilo): string
    {
        return implode('', array_filter([
            isset($estilo['negrita']) ? 'font-weight: '.($estilo['negrita'] ? 'bold' : 'normal').'; ' : null,
            isset($estilo['cursiva']) ? 'font-style: '.($estilo['cursiva'] ? 'italic' : 'normal').'; ' : null,
            isset($estilo['alineacion']) ? "text-align: {$estilo['alineacion']}; " : null,
            isset($estilo['espacio_antes']) ? "margin-top: {$estilo['espacio_antes']}pt; " : null,
            isset($estilo['espacio_despues']) ? "margin-bottom: {$estilo['espacio_despues']}pt; " : null,
        ]));
    }

    private static function generica(string $fuente): string
    {
        $fuente = mb_strtolower($fuente);
        if (str_contains($fuente, 'courier') || str_contains($fuente, 'mono')) {
            return 'monospace';
        }

        return in_array($fuente, self::FUENTES_SERIF, true) ? 'serif' : 'sans-serif';
    }

    /** Normal (letra y párrafo por defecto) y Título 1-3 del .docx. */
    public static function aplicarDocx(PhpWord $phpWord, ?array $estilos): void
    {
        $limpios = self::limpiar($estilos);
        $normal = $limpios['normal'] ?? [];
        if (isset($normal['fuente'])) {
            $phpWord->setDefaultFontName($normal['fuente']);
        }
        if (isset($normal['tamano'])) {
            $phpWord->setDefaultFontSize($normal['tamano']);
        }
        if ($parrafo = self::parrafoDocx($normal)) {
            $phpWord->setDefaultParagraphStyle($parrafo);
        }

        foreach (self::TAMANO_TITULO as $nivel => $tamano) {
            $estilo = $limpios["titulo_{$nivel}"] ?? null;
            if ($estilo === null) {
                continue;
            }
            $phpWord->addTitleStyle(
                $nivel,
                self::letraDocx($estilo) + ['bold' => true, 'size' => $tamano],
                self::parrafoDocx($estilo) + ['spaceBefore' => 240, 'spaceAfter' => 120]
            );
        }
    }

    /** Letra de la cita en el .docx (por defecto, cursiva). */
    public static function citaDocx(?array $estilos): array
    {
        return self::letraDocx(self::limpiar($estilos)['cita'] ?? []) + ['italic' => true];
    }

    /** Párrafo de la cita en el .docx. */
    public static function parrafoCitaDocx(?array $estilos): array
    {
        return self::parrafoDocx(self::limpiar($estilos)['cita'] ?? []);
    }

    private static function letraDocx(array $estilo): array
    {
        return array_filter([
            'name' => $estilo['fuente'] ?? null,
            'size' => $estilo['tamano'] ?? null,
            'color' => isset($estilo['color']) ? ltrim($estilo['color'], '#') : null,
            'bold' => $estilo['negrita'] ?? null,
            'italic' => $estilo['cursiva'] ?? null,
        ], fn ($valor) => $valor !== null);
    }

    /** Alineación y espacios en twips (1 pt = 20). */
    private static function parrafoDocx(array $estilo): array
    {
        return array_filter([
            'alignment' => match ($estilo['alineacion'] ?? null) {
                'center' => 'center',
                'right' => 'end',
                'justify' => 'both',
                'left' => 'start',
                default => null,
            },
            'spaceBefore' => isset($estilo['espacio_antes']) ? (int) round($estilo['espacio_antes'] * 20) : null,
            'spaceAfter' => isset($estilo['espacio_despues']) ? (int) round($estilo['espacio_despues'] * 20) : null,
        ], fn ($valor) => $valor !== null);
    }
}
