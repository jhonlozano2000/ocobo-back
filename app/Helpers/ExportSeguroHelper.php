<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * M11-01: exportación a Excel/CSV sin inyección de fórmulas. Un valor de texto que
 * empieza por = + - @ (o tabulador / retorno) se interpretaba como fórmula al abrir
 * el archivo (p. ej. =HYPERLINK(...) o DDE).
 */
class ExportSeguroHelper
{
    private const INICIOS_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Valor para CSV: los textos peligrosos se anteponen con comilla simple (OWASP).
     */
    public static function celdaCsv(mixed $valor): mixed
    {
        if (is_string($valor) && $valor !== '' && in_array($valor[0], self::INICIOS_PELIGROSOS, true)) {
            return "'".$valor;
        }

        return $valor;
    }

    /**
     * Fila para fputcsv con todos sus valores saneados.
     */
    public static function filaCsv(array $fila): array
    {
        return array_map([self::class, 'celdaCsv'], $fila);
    }

    /**
     * Escribe una celda de Excel: los textos se guardan como texto explícito, nunca como fórmula.
     */
    public static function setCelda(Worksheet $sheet, string $coordenada, mixed $valor): void
    {
        if (is_string($valor)) {
            $sheet->setCellValueExplicit($coordenada, $valor, DataType::TYPE_STRING);

            return;
        }

        $sheet->setCellValue($coordenada, $valor);
    }
}
