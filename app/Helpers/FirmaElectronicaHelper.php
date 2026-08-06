<?php

namespace App\Helpers;

use App\Services\Firma\TsaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class FirmaElectronicaHelper
{
    /**
     * Estampa un sello de firma electrónica en la última página de un PDF.
     *
     * @param  string  $disk  Disco donde está el archivo
     * @param  string  $path  Ruta relativa del archivo
     * @param  array  $datosFirma  Datos del firmante (nombre, cargo, fecha, hash)
     * @return array ['nuevo_path' => string, 'nuevo_hash' => string, 'timestamp_token' => ?string, 'timestamp_fecha' => ?string]
     */
    public static function estamparFirma(string $disk, string $path, array $datosFirma): array
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new \Exception('El documento original no existe.');
        }

        // Crear archivo temporal para leer con FPDI
        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempPath, $storage->get($path));

        try {
            $pdf = new Fpdi;
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

            // Solicitar timestamp RFC 3161
            $timestampToken = null;
            $timestampFecha = null;

            try {
                $timestampResult = self::solicitarTimestamp($nuevoHash);
                $timestampToken = $timestampResult['token'];
                $timestampFecha = $timestampResult['fecha'];
            } catch (\Exception $e) {
                Log::warning('Error al solicitar timestamp TSA', [
                    'hash' => $nuevoHash,
                    'error' => $e->getMessage(),
                ]);
                // La firma no falla por error de timestamp - se registra pero no bloquea
            }

            // Sobrescribir el archivo original con el firmado
            $storage->put($path, $nuevoContenido);

            return [
                'nuevo_path' => $path,
                'nuevo_hash' => $nuevoHash,
                'timestamp_token' => $timestampToken,
                'timestamp_fecha' => $timestampFecha,
            ];
        } finally {
            @unlink($tempPath); // Limpiar temp
        }
    }

    /**
     * Solicita un timestamp RFC 3161 para un hash SHA-256.
     *
     * @param  string  $hash  Hash SHA-256 en hexadecimal
     * @return array ['token' => string, 'fecha' => ?string]
     */
    public static function solicitarTimestamp(string $hash): array
    {
        $tsaService = new TsaService();
        $token = $tsaService->solicitarTimestamp($hash);

        // Obtener la fecha del token verificándolo
        $verificacion = $tsaService->verificarTimestamp($token, $hash);

        return [
            'token' => $token,
            'fecha' => $verificacion['fecha_firma'] ?? now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Verifica un token de timestamp RFC 3161.
     *
     * @param  string  $tokenBase64  Token TSR en base64
     * @param  string  $hash         Hash SHA-256 esperado en hexadecimal
     * @return array ['valido' => bool, 'fecha_firma' => ?string]
     */
    public static function verificarTimestamp(string $tokenBase64, string $hash): array
    {
        $tsaService = new TsaService();

        return $tsaService->verificarTimestamp($tokenBase64, $hash);
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
