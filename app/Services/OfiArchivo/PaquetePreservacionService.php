<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\OfiArchivo\OfiArchivoTransferenciaLote;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * B23 (diseño de expedientes §3.9; Acuerdo AGN 001/2024): paquete de preservación de la
 * transferencia secundaria.
 *
 * - Antes de enviar: cada folio está en un formato de preservación (el PDF solo si es PDF/A, leído
 *   de sus metadatos XMP; no se convierte nada), la cadena del índice y la del historial están
 *   íntegras y las versiones firmadas del índice y de la hoja de control superan su verificación.
 * - Al enviar: el expediente que no tiene índice (o hoja de control) firmado, o cuyo índice cambió
 *   después de firmarse con la cadena íntegra, se firma a nombre de quien envía.
 * - Recibido el lote: el paquete es un ZIP con el FUID, el acta, el índice (XML y PDF), la hoja de
 *   control y los archivos de cada expediente, más un manifiesto firmado con la huella de cada uno.
 */
class PaquetePreservacionService
{
    public const MOTIVO_FIRMA = 'transferencia_secundaria';

    /** Extensiones de texto plano que se aceptan sin firma de bytes. */
    private const TEXTO = ['xml', 'txt', 'csv'];

    private const BLOQUE = 1048576;

    public function __construct(
        private IndiceExpedienteService $indice,
        private HojaControlService $hojaControl,
        private ExpedienteEventosService $eventos,
    ) {}

    /**
     * Motivo por el que el expediente no puede ir en una transferencia secundaria; null si puede.
     * Lee los archivos, así que va de último en la validación.
     */
    public function motivo(OfiArchivoExpediente $expediente): ?string
    {
        if (IndiceExpedienteService::disponible()) {
            $cadena = $this->indice->verificarCadena($expediente);
            if (! $cadena['integra']) {
                $error = $cadena['errores'][0];

                return 'Su índice electrónico no está íntegro'.($error['numero_folio'] ? " en el folio {$error['numero_folio']}" : '')." ({$error['motivo']})";
            }
            $version = $this->ultimoIndice($expediente);
            if ($version && ! ($verificacion = $this->indice->verificarVersion($version))['valida']) {
                return "Su índice firmado no supera la verificación ({$verificacion['motivo']})";
            }
        }

        if (HojaControlService::disponible() && HojaControlService::aplica($expediente)) {
            $hoja = $this->hojaControl->ultimaVersion($expediente);
            if ($hoja && ! ($verificacion = $this->hojaControl->verificarVersion($hoja, $expediente))['valida']) {
                return "Su hoja de control firmada no supera la verificación ({$verificacion['motivo']})";
            }
        }

        if (ExpedienteEventosService::disponible() && ($rupturas = $this->eventos->verificar($expediente->id)) !== []) {
            return "Su historial no está íntegro (evento {$rupturas[0]['secuencia']}: {$rupturas[0]['problema']})";
        }

        $problemas = $expediente->documentos()->with('documentable')->where('activo', true)->orderBy('numero_folio')->get()
            ->map(fn (OfiArchivoExpedienteDocumento $doc) => ($problema = $this->problemaFormato($doc)) ? "el folio {$doc->numero_folio} ({$problema})" : null)
            ->filter()->values();
        if ($problemas->isNotEmpty()) {
            return 'No está en formatos de preservación: '.$problemas->take(5)->join(', ', ' y ')
                .($problemas->count() > 5 ? ' y '.($problemas->count() - 5).' más' : '');
        }

        return null;
    }

    /**
     * Firma el índice y la hoja de control que falten al enviar el lote. Se llama dentro de la
     * transacción del envío, ya validado el expediente.
     */
    public function firmarFaltantes(OfiArchivoExpediente $expediente, User $envia): void
    {
        $indice = null;
        if (IndiceExpedienteService::disponible()) {
            $indice = $this->ultimoIndice($expediente);
            if (! $indice || $indice->hash_cadena !== $this->indice->verificarCadena($expediente)['hash_final']) {
                $indice = $this->indice->versionar($expediente, $envia, self::MOTIVO_FIRMA);
            }
        }

        if (HojaControlService::disponible() && HojaControlService::aplica($expediente) && ! $this->hojaControl->ultimaVersion($expediente)) {
            $this->hojaControl->versionar($expediente, $envia, $expediente->soporte === 'hibrido' ? $indice : null, self::MOTIVO_FIRMA);
        }
    }

    /**
     * Arma el paquete en un archivo temporal y devuelve su ruta; quien lo pide lo borra.
     *
     * @throws ValidationException
     */
    public function generar(OfiArchivoTransferenciaLote $lote, User $usuario, TransferenciaLotesService $lotes): string
    {
        if ($lote->tipo !== 'secundaria' || $lote->estado !== 'recibido') {
            throw ValidationException::withMessages(['lote' => 'El paquete de preservación es de las transferencias secundarias recibidas']);
        }
        if (! ($verificacion = $lotes->verificar($lote))['integro']) {
            throw ValidationException::withMessages(['lote' => 'El lote no supera la verificación: '.implode('; ', $verificacion['problemas'])]);
        }

        $temporales = [];
        $archivos = [];
        $ruta = tempnam(sys_get_temp_dir(), 'paq');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::OVERWRITE);
        $agregarTexto = function (string $nombre, string $contenido) use ($zip, &$archivos) {
            $zip->addFromString($nombre, $contenido);
            $archivos[] = ['ruta' => $nombre, 'bytes' => strlen($contenido), 'sha256' => hash('sha256', $contenido)];
        };

        try {
            $agregarTexto("FUID_{$lote->codigo}.xlsx", (string) $lote->fuid);
            $agregarTexto("Acta_Transferencia_{$lote->codigo}.pdf", (string) $lote->acta);

            $expedientes = $lote->items()->with('expediente')->orderBy('id')->get()->pluck('expediente');
            foreach ($expedientes as $expediente) {
                $carpeta = self::nombreSeguro($expediente->numero_expediente);

                if (IndiceExpedienteService::disponible()) {
                    $version = $this->ultimoIndice($expediente);
                    if (! $version || ! $this->indice->verificarVersion($version)['valida']) {
                        throw ValidationException::withMessages(['lote' => "El índice firmado del expediente {$expediente->numero_expediente} no supera la verificación"]);
                    }
                    $agregarTexto("{$carpeta}/indice.xml", (string) $version->xml);
                    $agregarTexto("{$carpeta}/indice.pdf", (string) $version->pdf);
                }
                if (HojaControlService::disponible() && ($hoja = $this->hojaControl->ultimaVersion($expediente))) {
                    $agregarTexto("{$carpeta}/hoja_control.pdf", (string) $hoja->pdf);
                }

                $documentos = $expediente->documentos()->with('documentable')->where('activo', true)->orderBy('numero_folio')->get();
                foreach ($documentos as $doc) {
                    $ubicacion = ArchivoFolio::ubicacion($doc);
                    if (! $ubicacion) {
                        continue;
                    }
                    [$temporal, $hash, $bytes] = $this->copiar($ubicacion);
                    if ($temporal) {
                        $temporales[] = $temporal;
                    }
                    $esperado = ArchivoFolio::hashEsperado($doc);
                    if (! $temporal || ! $esperado || ! hash_equals($esperado, $hash)) {
                        throw ValidationException::withMessages(['lote' => "El folio {$doc->numero_folio} del expediente {$expediente->numero_expediente} ".($temporal ? 'no coincide con su huella' : 'no está en el repositorio').'; no se arma el paquete']);
                    }
                    $nombre = sprintf('%s/documentos/%04d_%s', $carpeta, $doc->numero_folio, self::nombreSeguro($ubicacion['nombre']));
                    $zip->addFile($temporal, $nombre);
                    $archivos[] = ['ruta' => $nombre, 'bytes' => $bytes, 'sha256' => $hash];
                }
            }

            $manifiesto = [
                'lote' => $lote->codigo,
                'tipo' => $lote->tipo,
                'entidad' => TransferenciaLotesService::entidad(),
                'codigo_verificacion' => $lote->codigo_verificacion,
                'generado_en' => now()->startOfSecond()->format('Y-m-d H:i:s'),
                'generado_por' => trim($usuario->nombres.' '.$usuario->apellidos) ?: 'Usuario #'.$usuario->id,
                'archivos' => $archivos,
            ];
            $manifiesto['firma'] = self::firmar($manifiesto);
            $zip->addFromString('manifiesto.json', json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->close();

            return $ruta;
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($ruta);
            throw $e;
        } finally {
            foreach ($temporales as $temporal) {
                @unlink($temporal);
            }
        }
    }

    /**
     * Firma electrónica interna (D2) del manifiesto: HMAC con una llave derivada de la de la
     * aplicación sobre el manifiesto sin su campo de firma.
     */
    public static function firmar(array $manifiesto): string
    {
        unset($manifiesto['firma']);

        return hash_hmac('sha256', json_encode($manifiesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), hash_hmac('sha256', 'ocobo-paquete-preservacion', (string) config('app.key')));
    }

    /** Problema de formato del folio; null si su archivo está en un formato de preservación. */
    public function problemaFormato(OfiArchivoExpedienteDocumento $doc): ?string
    {
        $ubicacion = ArchivoFolio::ubicacion($doc);
        if (! $ubicacion) {
            return null;
        }
        $disco = Storage::disk($ubicacion['disk']);
        $stream = $disco->exists($ubicacion['path']) ? $disco->readStream($ubicacion['path']) : null;
        if (! $stream) {
            return 'no está en el repositorio';
        }

        try {
            $inicio = (string) fread($stream, 128);
            $extension = Str::lower(pathinfo($ubicacion['nombre'], PATHINFO_EXTENSION));

            return match (true) {
                str_starts_with($inicio, '%PDF-') => $this->esPdfA($stream, $inicio) ? null : 'PDF que no es PDF/A',
                str_starts_with($inicio, "II*\0"), str_starts_with($inicio, "MM\0*"),
                str_starts_with($inicio, "\x89PNG\r\n\x1a\n"),
                str_starts_with($inicio, "\xFF\xD8\xFF"),
                str_starts_with($inicio, "\0\0\0\x0CjP  \r\n\x87\n"),
                str_starts_with($inicio, 'fLaC'),
                str_starts_with($inicio, 'RIFF') && substr($inicio, 8, 4) === 'WAVE',
                substr($inicio, 4, 4) === 'ftyp',
                str_starts_with($inicio, "PK\x03\x04") && str_contains($inicio, 'mimetypeapplication/vnd.oasis.opendocument') => null,
                in_array($extension, self::TEXTO, true) => str_contains($inicio, "\0") ? 'texto con contenido binario' : null,
                default => 'formato no admitido'.($extension !== '' ? ": {$extension}" : ''),
            };
        } finally {
            fclose($stream);
        }
    }

    /**
     * PDF/A-1 y PDF/A-2 prohíben comprimir el flujo de metadatos, así que la declaración
     * pdfaid:part del XMP se lee en los bytes del archivo, por bloques y con solape.
     *
     * @param  resource  $stream
     */
    private function esPdfA($stream, string $inicio): bool
    {
        $patron = '/pdfaid:part\s*(=\s*["\']|>)\s*[1-4]/';
        $resto = $inicio;
        while (true) {
            if (preg_match($patron, $resto)) {
                return true;
            }
            if (feof($stream)) {
                return false;
            }
            $resto = substr($resto, -64).fread($stream, self::BLOQUE);
        }
    }

    private function ultimoIndice(OfiArchivoExpediente $expediente): ?OfiArchivoExpedienteIndiceVersion
    {
        return OfiArchivoExpedienteIndiceVersion::where('expediente_id', $expediente->id)->latest('version')->first();
    }

    /**
     * Copia el binario a un temporal y calcula su huella en la misma lectura.
     *
     * @return array{0: ?string, 1: ?string, 2: int}
     */
    private function copiar(array $ubicacion): array
    {
        $disco = Storage::disk($ubicacion['disk']);
        $origen = $disco->exists($ubicacion['path']) ? $disco->readStream($ubicacion['path']) : null;
        if (! $origen) {
            return [null, null, 0];
        }

        $temporal = tempnam(sys_get_temp_dir(), 'pqf');
        $destino = fopen($temporal, 'wb');
        $contexto = hash_init('sha256');
        $bytes = 0;
        while (! feof($origen)) {
            $bloque = (string) fread($origen, self::BLOQUE);
            hash_update($contexto, $bloque);
            fwrite($destino, $bloque);
            $bytes += strlen($bloque);
        }
        fclose($origen);
        fclose($destino);

        return [$temporal, hash_final($contexto), $bytes];
    }

    private static function nombreSeguro(string $nombre): string
    {
        return trim(preg_replace('/[^\pL\pN._-]+/u', '_', $nombre), '_') ?: 'archivo';
    }
}
