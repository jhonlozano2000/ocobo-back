<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Storage;

/**
 * Dónde está el binario de un folio y qué huella debe tener: el archivo cargado al expediente o el
 * del radicado incorporado.
 */
final class ArchivoFolio
{
    /** Disco donde está el binario de un radicado incorporado al expediente. */
    public const DISCOS_RADICADOS = [
        VentanillaRadicaReci::class => 'radicados_recibidos',
        VentanillaRadicaEnviados::class => 'radicados_enviados',
        VentanillaRadicaInterno::class => 'radicados_internos',
    ];

    /**
     * @return array{disk: string, path: string, nombre: string}|null
     */
    public static function ubicacion(OfiArchivoExpedienteDocumento $doc): ?array
    {
        if ($doc->archivo_path) {
            return [
                'disk' => CierreExpedienteService::DISK,
                'path' => $doc->archivo_path,
                'nombre' => $doc->nombre_original ?: basename($doc->archivo_path),
            ];
        }

        $disco = self::DISCOS_RADICADOS[$doc->documentable_type] ?? null;
        $path = $doc->documentable?->archivo_digital;
        if (! $disco || ! $path) {
            return null;
        }

        return ['disk' => $disco, 'path' => $path, 'nombre' => basename($path)];
    }

    /** Huella del folio (documentos cargados) o, si no tiene, la del radicado incorporado. */
    public static function hashEsperado(OfiArchivoExpedienteDocumento $doc): ?string
    {
        return $doc->hash_sha256 ?: ($doc->documentable?->hash_sha256 ?: null);
    }

    /** SHA-256 del binario leído por partes; null si no está en el disco. */
    public static function hashCalculado(array $ubicacion): ?string
    {
        $disco = Storage::disk($ubicacion['disk']);
        $stream = $disco->exists($ubicacion['path']) ? $disco->readStream($ubicacion['path']) : null;
        if (! $stream) {
            return null;
        }

        $contexto = hash_init('sha256');
        hash_update_stream($contexto, $stream);
        fclose($stream);

        return hash_final($contexto);
    }
}
