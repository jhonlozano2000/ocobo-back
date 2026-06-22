<?php

namespace App\Services\VentanillaUnica;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Alignment;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Picqer\Barcode\BarcodeGeneratorPNG;

class RotuloPngService
{
    protected ImageManager $imageManager;

    protected BarcodeGeneratorPNG $barcodeGenerator;

    protected string $fontBold;

    protected string $fontRegular;

    public function __construct()
    {
        $this->imageManager = ImageManager::usingDriver(Driver::class);
        $this->barcodeGenerator = new BarcodeGeneratorPNG;
        $this->fontBold = storage_path('fonts/arialbd.ttf');
        $this->fontRegular = storage_path('fonts/arial.ttf');
    }

    /**
     * Genera una imagen PNG con el rótulo del radicado.
     * Incluye hash SHA-256 para integridad (ISO 27001 A.10.1.2).
     *
     * @param  array  $radicadoData  Datos del radicado
     * @return string Ruta del archivo PNG generado
     */
    public function generarRotulo(array $radicadoData): string
    {
        $ancho = 500;
        $alto = 680;

        $img = $this->imageManager->createImage($ancho, $alto)->fill('ffffff');

        $colorHeader = '1a1a2e';
        $colorTexto = '000000';
        $colorSecundario = '555555';
        $colorBlanco = 'ffffff';
        $colorLinea = 'c8c8c8';
        $colorHashBg = 'f0f0f0';

        // Header background
        $img->drawRectangle(function ($rect) use ($ancho, $colorHeader) {
            $rect->at(0, 0);
            $rect->size($ancho, 60);
            $rect->background($colorHeader);
        });

        // Header text
        $img->text('OCOBO - RADICADO', $ancho / 2, 20, function (FontFactory $font) use ($colorBlanco) {
            $font->filepath($this->fontBold);
            $font->size(18);
            $font->color($colorBlanco);
            $font->align(Alignment::CENTER, Alignment::TOP);
        });

        $y = 85;

        // Numero de radicado
        $numRadicado = $radicadoData['num_radicado'] ?? '';
        $img->text($numRadicado, $ancho / 2, $y, function (FontFactory $font) use ($colorTexto) {
            $font->filepath($this->fontBold);
            $font->size(24);
            $font->color($colorTexto);
            $font->align(Alignment::CENTER, Alignment::TOP);
        });
        $y += 40;

        // Fecha
        $fecha = $radicadoData['fecha_radicado'] ?? now()->format('Y-m-d H:i:s');
        $img->text($fecha, $ancho / 2, $y, function (FontFactory $font) use ($colorSecundario) {
            $font->filepath($this->fontRegular);
            $font->size(13);
            $font->color($colorSecundario);
            $font->align(Alignment::CENTER, Alignment::TOP);
        });
        $y += 30;

        // Linea separadora
        $img->drawLine(function ($line) use ($y, $ancho, $colorLinea) {
            $line->from(30, $y);
            $line->to($ancho - 30, $y);
            $line->color($colorLinea);
        });
        $y += 25;

        // Remitente
        $img->text('REMITENTE:', 30, $y, function (FontFactory $font) use ($colorSecundario) {
            $font->filepath($this->fontBold);
            $font->size(11);
            $font->color($colorSecundario);
        });
        $y += 20;

        $remitenteNombre = $radicadoData['remitente_nombre'] ?? '';
        $remitenteEmail = $radicadoData['remitente_email'] ?? '';
        $remitenteLinea = $remitenteNombre.($remitenteEmail ? " <{$remitenteEmail}>" : '');
        $remitenteLinea = mb_strlen($remitenteLinea) > 70 ? mb_substr($remitenteLinea, 0, 70).'...' : $remitenteLinea;
        $img->text($remitenteLinea, 30, $y, function (FontFactory $font) use ($colorTexto) {
            $font->filepath($this->fontRegular);
            $font->size(11);
            $font->color($colorTexto);
        });
        $y += 30;

        // Asunto
        $img->text('ASUNTO:', 30, $y, function (FontFactory $font) use ($colorSecundario) {
            $font->filepath($this->fontBold);
            $font->size(11);
            $font->color($colorSecundario);
        });
        $y += 20;

        $asunto = $radicadoData['asunto'] ?? '';
        $asuntoCorto = mb_strlen($asunto) > 75 ? mb_substr($asunto, 0, 75).'...' : $asunto;
        $img->text($asuntoCorto, 30, $y, function (FontFactory $font) use ($colorTexto) {
            $font->filepath($this->fontRegular);
            $font->size(11);
            $font->color($colorTexto);
        });
        $y += 40;

        // Barcode CODE128
        try {
            $barcodePng = $this->barcodeGenerator->getBarcode(
                $numRadicado,
                BarcodeGeneratorPNG::TYPE_CODE_128,
                2,
                80
            );
            $img->insert($barcodePng, x: (int) (($ancho - 300) / 2), y: $y);
            $y += 100;
        } catch (\Exception $e) {
            Log::warning('RotuloPngService: Error generando barcode', ['error' => $e->getMessage()]);
            $y += 10;
        }

        // Hash SHA-256 (ISO 27001 A.10.1.2 - Control de integridad)
        $hashSha256 = $radicadoData['hash_sha256'] ?? '';
        if (! empty($hashSha256)) {
            $y += 5;

            // Background box for hash
            $img->drawRectangle(function ($rect) use ($y, $ancho, $colorHashBg) {
                $rect->at(25, $y);
                $rect->size($ancho - 50, 55);
                $rect->background($colorHashBg);
            });

            $img->text('HASH SHA-256 (ISO 27001):', 30, $y + 5, function (FontFactory $font) use ($colorSecundario) {
                $font->filepath($this->fontBold);
                $font->size(10);
                $font->color($colorSecundario);
            });

            // Split hash into two lines if too long (64 chars)
            $hashLine1 = mb_substr($hashSha256, 0, 32);
            $hashLine2 = mb_substr($hashSha256, 32);
            $img->text($hashLine1, 30, $y + 20, function (FontFactory $font) use ($colorTexto) {
                $font->filepath($this->fontRegular);
                $font->size(9);
                $font->color($colorTexto);
            });
            if (! empty($hashLine2)) {
                $img->text($hashLine2, 30, $y + 33, function (FontFactory $font) use ($colorTexto) {
                    $font->filepath($this->fontRegular);
                    $font->size(9);
                    $font->color($colorTexto);
                });
            }
            $y += 60;
        }

        // Clasificacion documental
        $clasificacion = $radicadoData['clasificacion'] ?? '';
        if (! empty($clasificacion)) {
            $y += 5;
            $img->text('CLASIFICACION:', 30, $y, function (FontFactory $font) use ($colorSecundario) {
                $font->filepath($this->fontBold);
                $font->size(11);
                $font->color($colorSecundario);
            });
            $y += 20;
            $img->text($clasificacion, 30, $y, function (FontFactory $font) use ($colorTexto) {
                $font->filepath($this->fontRegular);
                $font->size(11);
                $font->color($colorTexto);
            });
        }

        // Guardar archivo
        $nombreArchivo = 'rotulo_'.preg_replace('/[^a-zA-Z0-9-]/', '_', $numRadicado).'_'.now()->format('Ymd_His').'.png';
        $directorio = 'radicados_recibidos/'.$numRadicado;
        $rutaCompleta = storage_path("app/{$directorio}");

        if (! is_dir($rutaCompleta)) {
            mkdir($rutaCompleta, 0755, true);
        }

        $rutaArchivo = $rutaCompleta.'/'.$nombreArchivo;
        $img->save($rutaArchivo);

        return "{$directorio}/{$nombreArchivo}";
    }
}
