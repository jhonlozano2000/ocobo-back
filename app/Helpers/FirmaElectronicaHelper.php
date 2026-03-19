<?php

namespace App\Helpers;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;

class FirmaElectronicaHelper
{
    /**
     * Estampa un sello de firma electrónica en la última página de un PDF.
     *
     * @param string $disk Disco donde está el archivo
     * @param string $path Ruta relativa del archivo
     * @param array $datosFirma Datos del firmante (nombre, cargo, fecha, hash)
     * @return array ['nuevo_path' => string, 'nuevo_hash' => string]
     */
    public static function estamparFirma(string $disk, string $path, array $datosFirma): array
    {
        $storage = Storage::disk($disk);
        
        if (!$storage->exists($path)) {
            throw new \Exception("El documento original no existe.");
        }

        // Crear archivo temporal para leer con FPDI
        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempPath, $storage->get($path));

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($tempPath);

            // Importar todas las páginas
            for ($n = 1; $n <= $pageCount; $n++) {
                $templateId = $pdf->importPage($n);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
                
                // Si es la última página, agregar el sello
                if ($n === $pageCount) {
                    self::dibujarSello($pdf, $datosFirma, $size);
                }
            }

            $nuevoContenido = $pdf->Output('S');
            $nuevoHash = hash('sha256', $nuevoContenido);

            // Sobrescribir el archivo original con el firmado
            $storage->put($path, $nuevoContenido);

            return [
                'nuevo_path' => $path,
                'nuevo_hash' => $nuevoHash
            ];

        } finally {
            @unlink($tempPath); // Limpiar temp
        }
    }

    /**
     * Dibuja el rectángulo con la información de la firma al final de la página.
     */
    private static function dibujarSello(Fpdi $pdf, array $datosFirma, array $size)
    {
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(50, 50, 50);
        
        // Coordenadas para el sello (esquina inferior izquierda)
        $x = 15;
        $y = $size['height'] - 35; // 35mm desde abajo

        // Caja del sello
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Rect($x, $y, 180, 25, 'DF');

        // Texto del sello
        $pdf->SetXY($x + 5, $y + 5);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 4, utf8_decode('FIRMADO ELECTRÓNICAMENTE (Ley 527 de 1999)'), 0, 1);
        
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetX($x + 5);
        $pdf->Cell(0, 4, utf8_decode("Firmante: {$datosFirma['nombre']} - {$datosFirma['cargo']}"), 0, 1);
        
        $pdf->SetX($x + 5);
        $pdf->Cell(0, 4, utf8_decode("Fecha: {$datosFirma['fecha']}"), 0, 1);

        $pdf->SetX($x + 5);
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell(0, 4, utf8_decode("Integridad Original (SHA-256): {$datosFirma['hash_original']}"), 0, 1);
    }
}
