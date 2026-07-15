<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ReportesExportService
{
    /**
     * Export data as Excel file and return the file path.
     */
    public function exportarExcel(array $data, string $nombre, array $columnas = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = $columnas ?: array_keys(($data['datos'][0] ?? []));
        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8EAF6');
            $sheet->getStyle($col . '1')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row = 2;
        foreach (($data['datos'] ?? []) as $item) {
            foreach ($headers as $i => $header) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $value = is_array($item) ? ($item[$header] ?? $item[strtolower($header)] ?? '') : '';
                $sheet->setCellValue($col . $row, $value);
            }
            $row++;
        }

        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $path = $this->generarRuta($nombre, 'xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        if (!file_exists($path)) {
            throw new \RuntimeException("No se pudo generar el archivo Excel.");
        }

        return $path;
    }

    /**
     * Export data as PDF file and return the file path.
     */
    public function exportarPDF(array $data, string $nombre, string $orientacion = 'portrait'): string
    {
        $pdf = Pdf::loadView('reportes.export-pdf', [
            'titulo' => $data['titulo'] ?? $nombre,
            'fecha_generacion' => $data['fecha_generacion'] ?? now()->format('Y-m-d H:i:s'),
            'total' => $data['total'] ?? count($data['datos'] ?? []),
            'columnas' => $data['columnas'] ?? [],
            'datos' => $data['datos'] ?? [],
            'resumen' => $data['resumen'] ?? [],
        ]);

        $pdf->setPaper('letter', $orientacion);

        $path = $this->generarRuta($nombre, 'pdf');
        $bytes = file_put_contents($path, $pdf->output());

        if ($bytes === false) {
            throw new \RuntimeException("No se pudo generar el archivo PDF.");
        }

        return $path;
    }

    /**
     * Stream CSV to response.
     */
    public function exportarCSV(array $data, string $nombre, array $columnas = [])
    {
        $headers = $columnas ?: array_keys(($data['datos'][0] ?? []));
        $sanitizado = self::sanitizarNombre($nombre);

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach (($data['datos'] ?? []) as $item) {
                $row = [];
                foreach ($headers as $header) {
                    $row[] = is_array($item) ? ($item[$header] ?? $item[strtolower($header)] ?? '') : '';
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $sanitizado . '_' . date('Ymd_His') . '.csv"',
        ]);
    }

    protected function generarRuta(string $nombre, string $extension): string
    {
        $dir = storage_path('app/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $sanitizado = self::sanitizarNombre($nombre);

        return $dir . '/' . $sanitizado . '_' . date('Ymd_His') . '.' . $extension;
    }

    protected static function sanitizarNombre(string $nombre): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $nombre);
    }
}
