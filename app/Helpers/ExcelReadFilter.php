<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Filtro de lectura para PhpSpreadsheet para mitigar consumo excesivo de memoria (DoS)
 * y limitar filas leídas en importaciones TRD y TVD (M10-05).
 */
class ExcelReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $maxRows = 5000,
        private readonly int $startRow = 1
    ) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->maxRows;
    }
}
