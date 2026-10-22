<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\Configuracion\ConfigVarias;
use Illuminate\Support\Facades\Storage;

/**
 * Logo de la entidad (configuración general) para el membrete de los documentos: el encabezado de página
 * del editor, del PDF y del .docx.
 *
 * Está en un disco privado que ni el navegador ni dompdf pueden pedir por URL, así que se entrega en bytes
 * o como data URI. Solo si es una imagen de verdad (por el contenido, no por la extensión) y no muy pesada.
 */
class LogoEntidadService
{
    /** Logos más pesados no se usan: viajan en cada exportación y en cada hoja del PDF. */
    public const MAX_BYTES = 300 * 1024;

    private const TIPOS = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    private array|false|null $logo = null;

    /** @return array{mime: string, bytes: string, ancho: int, alto: int}|null */
    public function archivo(): ?array
    {
        if ($this->logo === null) {
            $this->logo = $this->leer(ConfigVarias::getInformacionEmpresa()['logo_empresa'] ?? null) ?? false;
        }

        return $this->logo ?: null;
    }

    public function dataUri(): ?string
    {
        $logo = $this->archivo();

        return $logo ? 'data:'.$logo['mime'].';base64,'.base64_encode($logo['bytes']) : null;
    }

    private function leer(?string $archivo): ?array
    {
        $disco = Storage::disk('otros_archivos');
        if (! $archivo || ! $disco->exists($archivo) || $disco->size($archivo) > self::MAX_BYTES) {
            return null;
        }
        $bytes = $disco->get($archivo);
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'], self::TIPOS, true)) {
            return null;
        }

        return ['mime' => $info['mime'], 'bytes' => $bytes, 'ancho' => $info[0], 'alto' => $info[1]];
    }
}
