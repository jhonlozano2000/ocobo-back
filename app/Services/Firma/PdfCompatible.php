<?php

namespace App\Services\Firma;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

/**
 * La versión libre de FPDI no lee los PDF 1.5+ con tablas de referencias comprimidas (los generan
 * Word, los escáneres...). Antes de estampar, qpdf reescribe una copia con la tabla clásica sin tocar
 * el contenido; si el original estaba protegido, la copia lo sigue estando y FPDI lo rechaza.
 */
class PdfCompatible
{
    public const MENSAJE_SIN_QPDF = 'No se pudo preparar el PDF para firmarlo. Contacte al administrador.';

    /**
     * Ruta que FPDI puede leer: la original o una copia convertida ($path.'.fpdi.pdf') que borra quien llama.
     */
    public static function paraFpdi(string $path): string
    {
        if (! self::usaReferenciasComprimidas($path)) {
            return $path;
        }

        $salida = $path.'.fpdi.pdf';
        try {
            $resultado = Process::timeout(60)->run([config('firma.qpdf_path'), '--object-streams=disable', $path, $salida]);
            // 3 = terminó con advertencias; la copia es válida
            $convertido = in_array($resultado->exitCode(), [0, 3], true) && is_file($salida);
            $detalle = $resultado->errorOutput();
        } catch (\Throwable $e) {
            $convertido = false;
            $detalle = $e->getMessage();
        }

        if (! $convertido) {
            @unlink($salida);
            Log::error('qpdf no pudo preparar el PDF para firmarlo', ['qpdf' => config('firma.qpdf_path'), 'error' => $detalle]);

            throw new \Exception(self::MENSAJE_SIN_QPDF);
        }

        return $salida;
    }

    private static function usaReferenciasComprimidas(string $path): bool
    {
        try {
            (new Fpdi)->setSourceFile($path);
        } catch (CrossReferenceException $e) {
            return $e->getCode() === CrossReferenceException::COMPRESSED_XREF;
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
