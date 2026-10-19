<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndice;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\User;
use App\Services\Firma\TsaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use setasign\Fpdi\Fpdi;

/**
 * Índice electrónico del expediente (Acuerdo 003 de 2015 AGN).
 *
 * M08-09: el cierre, el PDF y el XML se construyen con las mismas filas, de modo que el
 * `hash_indice` guardado al cerrar se puede recalcular y comparar contra lo que muestra el índice.
 * Los folios retirados se listan (la foliación no se reutiliza) pero marcados como retirados.
 *
 * B10: además, cada folio deja al incorporarse una fila encadenada en `ofi_archivo_expediente_indice`
 * y cada cierre congela el índice (XML + PDF) en una versión con firma electrónica interna (D2).
 */
class IndiceExpedienteService
{
    private static ?bool $disponible = null;

    /** Las tablas llegan con la migración 2026_10_19_130000. */
    public static function disponible(): bool
    {
        return self::$disponible ??= Schema::hasTable('ofi_archivo_expediente_indice');
    }

    /** Escribe la fila del folio al final de la cadena del índice de su expediente. */
    public function agregar(OfiArchivoExpedienteDocumento $doc): OfiArchivoExpedienteIndice
    {
        // El expediente ya está bloqueado al foliar (creating); en el backfill no hay concurrencia
        $anterior = OfiArchivoExpedienteIndice::where('expediente_id', $doc->expediente_id)
            ->orderByDesc('orden')->value('hash_cadena');
        $doc->loadMissing('documentable');

        $fila = new OfiArchivoExpedienteIndice([
            'expediente_id' => $doc->expediente_id,
            'documento_id' => $doc->id,
            'orden' => $doc->numero_folio,
            'numero_folio' => $doc->numero_folio,
            'tipo_documental_id' => $doc->tipo_documental_id,
            'tipo_documental' => mb_substr((string) ($doc->tipo_documental ?: 'Sin tipo documental'), 0, 200),
            'origen' => mb_substr($this->origen($doc), 0, 255),
            'asunto' => mb_substr((string) $this->asunto($doc), 0, 500) ?: null,
            'fecha_documento' => $doc->fecha_documento ?: null,
            'fecha_incorporacion' => ($doc->fecha_incorporacion ?? $doc->created_at ?? now())->format('Y-m-d H:i:s'),
            'hash_sha256' => $this->hashDocumento($doc),
            'algoritmo' => 'SHA-256',
            'formato' => $doc->formato_archivo ? mb_substr($doc->formato_archivo, 0, 20) : null,
            'tamano_bytes' => $doc->tamano_bytes,
            'hash_anterior' => $anterior,
        ]);
        if (! $doc->activo) {
            $fila->anulado_en = $doc->updated_at ?? now();
            $fila->motivo_anulacion = 'Retirado antes de habilitar la cadena del índice';
        }
        $fila->hash_cadena = self::huella($fila);
        $fila->save();

        return $fila;
    }

    /** El folio retirado sigue en el índice, anulado (la foliación no se reutiliza). */
    public function anular(OfiArchivoExpedienteDocumento $doc, ?string $motivo = null): void
    {
        OfiArchivoExpedienteIndice::where('documento_id', $doc->id)->whereNull('anulado_en')->first()?->update([
            'anulado_en' => now(),
            'motivo_anulacion' => $motivo ? mb_substr($motivo, 0, 500) : 'Retirado del expediente',
            'anulado_por' => Auth::id(),
        ]);
    }

    /** B07: tipificar el folio actualiza el índice sin tocar la cadena. */
    public function actualizarTipo(OfiArchivoExpedienteDocumento $doc): void
    {
        OfiArchivoExpedienteIndice::where('documento_id', $doc->id)->first()?->update([
            'tipo_documental_id' => $doc->tipo_documental_id,
            'tipo_documental' => mb_substr((string) $doc->tipo_documental, 0, 200),
        ]);
    }

    public static function huella(OfiArchivoExpedienteIndice $fila): string
    {
        $datos = [];
        foreach (OfiArchivoExpedienteIndice::CAMPOS_ENCADENADOS as $campo) {
            $valor = $fila->getAttribute($campo);
            $datos[$campo] = $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d H:i:s') : $valor;
        }

        return hash('sha256', ($fila->hash_anterior ?? '').'|'.json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Recorre la cadena del índice: cada fila debe enlazar con la anterior, conservar su huella
     * y la del archivo del folio, y todo folio del expediente debe tener su fila.
     *
     * @return array{disponible: bool, integra: ?bool, total: int, hash_final: ?string, errores: list<array{numero_folio: ?int, motivo: string}>}
     */
    public function verificarCadena(OfiArchivoExpediente $expediente): array
    {
        if (! self::disponible()) {
            return ['disponible' => false, 'integra' => null, 'total' => 0, 'hash_final' => null, 'errores' => []];
        }

        $filas = OfiArchivoExpedienteIndice::where('expediente_id', $expediente->id)->orderBy('orden')->get();
        $documentos = $expediente->documentos()->with('documentable')->get()->keyBy('id');
        $errores = [];
        $anterior = null;

        foreach ($filas as $fila) {
            if ($fila->hash_anterior !== $anterior) {
                $errores[] = ['numero_folio' => $fila->numero_folio, 'motivo' => 'No enlaza con la fila anterior del índice'];
            }
            if (! hash_equals($fila->hash_cadena, self::huella($fila))) {
                $errores[] = ['numero_folio' => $fila->numero_folio, 'motivo' => 'Los datos de la fila fueron alterados'];
            }
            $doc = $documentos->get($fila->documento_id);
            if (! $doc) {
                $errores[] = ['numero_folio' => $fila->numero_folio, 'motivo' => 'El documento del folio ya no existe'];
            } elseif ($fila->hash_sha256 !== $this->hashDocumento($doc)) {
                $errores[] = ['numero_folio' => $fila->numero_folio, 'motivo' => 'La huella del documento no coincide con la del índice'];
            }
            $anterior = $fila->hash_cadena;
        }

        foreach ($documentos->diffKeys($filas->keyBy('documento_id')) as $doc) {
            $errores[] = ['numero_folio' => $doc->numero_folio, 'motivo' => 'El folio no está en el índice'];
        }

        return [
            'disponible' => true,
            'integra' => $errores === [],
            'total' => $filas->count(),
            'hash_final' => $anterior,
            'errores' => $errores,
        ];
    }

    /**
     * Congela el índice del expediente (ya cerrado) en una versión firmada.
     * Firma electrónica interna (D2): HMAC con la llave de la aplicación sobre las huellas del XML
     * y del PDF, el firmante, la fecha y el código de verificación. El sello de tiempo RFC 3161
     * es opcional (ARCHIVO_INDICE_TSA) y su falla no impide el cierre.
     */
    public function versionar(OfiArchivoExpediente $expediente, User $firmante, string $motivo = 'cierre'): OfiArchivoExpedienteIndiceVersion
    {
        $firma = [
            'version' => (int) OfiArchivoExpedienteIndiceVersion::where('expediente_id', $expediente->id)->max('version') + 1,
            'firmante' => trim($firmante->nombres.' '.$firmante->apellidos) ?: 'Usuario #'.$firmante->id,
            'firmado_en' => now()->startOfSecond(),
            'codigo' => self::codigoVerificacion(),
        ];
        $cadena = $this->verificarCadena($expediente);

        $xml = $this->xml($expediente, $firma);
        $pdf = $this->pdf($expediente, $firma);

        $version = new OfiArchivoExpedienteIndiceVersion([
            'expediente_id' => $expediente->id,
            'version' => $firma['version'],
            'motivo' => $motivo,
            'total_folios' => $expediente->documentos()->count(),
            'hash_indice' => $expediente->hash_indice ?: $this->hash($this->filas($expediente)),
            'hash_cadena' => $cadena['hash_final'],
            'xml' => $xml,
            'hash_xml' => hash('sha256', $xml),
            'pdf' => $pdf,
            'hash_pdf' => hash('sha256', $pdf),
            'firma_tipo' => 'electronica_interna',
            'firmado_por' => $firmante->id,
            'firmante' => mb_substr($firma['firmante'], 0, 255),
            'firmado_en' => $firma['firmado_en'],
            'codigo_verificacion' => $firma['codigo'],
        ]);
        $version->firma = self::firmar($version);

        if (config('firma.tsa_indice_expediente')) {
            try {
                $sello = app(TsaService::class)->solicitarTimestamp($version->hash_xml);
                $version->sello_tiempo = $sello['token'];
                $version->sello_tiempo_fecha = $sello['fecha'];
            } catch (\Throwable $e) {
                Log::warning('Índice del expediente firmado sin sello de tiempo', ['expediente_id' => $expediente->id, 'error' => $e->getMessage()]);
            }
        }

        $version->save();

        app(ExpedienteEventosService::class)->registrar($expediente->id, 'indice_firmado', [
            'version' => $version->version,
            'codigo_verificacion' => $version->codigo_verificacion,
            'hash_xml' => $version->hash_xml,
            'hash_pdf' => $version->hash_pdf,
            'cadena_integra' => $cadena['integra'],
        ]);

        return $version;
    }

    /** @return array{valida: bool, motivo: ?string} */
    public function verificarVersion(OfiArchivoExpedienteIndiceVersion $version): array
    {
        $motivo = match (true) {
            ! hash_equals($version->hash_xml, hash('sha256', (string) $version->xml)) => 'El XML no coincide con su huella',
            ! hash_equals($version->hash_pdf, hash('sha256', (string) $version->pdf)) => 'El PDF no coincide con su huella',
            ! hash_equals($version->firma, self::firmar($version)) => 'La firma no corresponde a la versión',
            default => null,
        };

        return ['valida' => $motivo === null, 'motivo' => $motivo];
    }

    /**
     * Filas del índice en orden de folio.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(OfiArchivoExpediente $expediente): Collection
    {
        return $expediente->documentos()->with('documentable')->orderBy('numero_folio')->get()
            ->map(fn (OfiArchivoExpedienteDocumento $doc) => [
                'numero_folio' => (int) $doc->numero_folio,
                'fecha_incorporacion' => $doc->fecha_incorporacion?->toDateTimeString(),
                'tipo_documental' => $doc->tipo_documental,
                'origen' => $this->origen($doc),
                'asunto' => $this->asunto($doc),
                'hash_sha256' => $this->hashDocumento($doc),
                'activo' => (bool) $doc->activo,
            ])
            ->values();
    }

    /** Hash SHA-256 del índice; es el valor que se sella en `hash_indice` al cerrar. */
    public function hash(Collection $filas): string
    {
        return hash('sha256', json_encode($filas->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array{version: int, firmante: string, firmado_en: \DateTimeInterface, codigo: string}|null  $firma  datos de la versión firmada (B10)
     */
    public function pdf(OfiArchivoExpediente $expediente, ?array $firma = null): string
    {
        $filas = $this->filas($expediente);
        $txt = fn (?string $s) => mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');

        $pdf = new Fpdi;
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, $txt('ÍNDICE ELECTRÓNICO DE EXPEDIENTE - SGDEA OCOBO'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, $txt('Dependencia: '.($expediente->dependencia_nom ?? $expediente->dependencia?->nom_organico)), 0, 1);
        $pdf->Cell(0, 7, $txt('Serie/Subserie: '.$expediente->serieTrd?->nom), 0, 1);
        $pdf->Cell(0, 7, $txt("No. Expediente: {$expediente->numero_expediente} | Nombre: {$expediente->nombre_expediente}"), 0, 1);
        $pdf->Cell(0, 7, $txt('Fecha de apertura: '.$expediente->fecha_apertura?->format('Y-m-d')
            .' | Estado: '.$expediente->estado
            .($expediente->fecha_cierre ? ' | Fecha de cierre: '.$expediente->fecha_cierre->format('Y-m-d') : '')), 0, 1);
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(14, 8, 'Folio', 1, 0, 'C', true);
        $pdf->Cell(30, 8, $txt('Fecha incorp.'), 1, 0, 'C', true);
        $pdf->Cell(55, 8, 'Origen', 1, 0, 'C', true);
        $pdf->Cell(66, 8, 'Asunto / Detalle', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Estado', 1, 0, 'C', true);
        $pdf->Cell(92, 8, 'Huella digital (SHA-256)', 1, 1, 'C', true);

        foreach ($filas as $fila) {
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor($fila['activo'] ? 0 : 150);
            $pdf->Cell(14, 8, (string) $fila['numero_folio'], 1, 0, 'C');
            $pdf->Cell(30, 8, substr((string) $fila['fecha_incorporacion'], 0, 10), 1, 0, 'C');
            $pdf->Cell(55, 8, $txt(mb_substr($fila['origen'], 0, 34)), 1, 0, 'L');
            $pdf->Cell(66, 8, $txt(mb_substr((string) $fila['asunto'] ?: 'Sin asunto', 0, 42)), 1, 0, 'L');
            $pdf->Cell(20, 8, $fila['activo'] ? 'Activo' : 'Retirado', 1, 0, 'C');
            $pdf->SetFont('Courier', '', 6.5);
            $pdf->Cell(92, 8, $fila['hash_sha256'] ?? 'Sin hash registrado', 1, 1, 'L');
        }
        $pdf->SetTextColor(0);

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 8);
        $hashActual = $this->hash($filas);
        $pdf->Cell(0, 5, $txt('Hash del índice (SHA-256): ').$hashActual, 0, 1);
        if ($expediente->hash_indice) {
            $coincide = hash_equals($expediente->hash_indice, $hashActual);
            $pdf->Cell(0, 5, $txt('Hash sellado al cierre: ').$expediente->hash_indice, 0, 1);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $txt($coincide
                ? 'Verificación: el índice coincide con el sellado al cierre.'
                : 'Verificación: el índice NO coincide con el sellado al cierre.'), 0, 1);
        }
        $cadena = $this->verificarCadena($expediente);
        if ($cadena['disponible']) {
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 5, $txt('Cadena de integridad del índice: '.($cadena['integra']
                ? 'íntegra ('.$cadena['total'].' folios), huella final '.$cadena['hash_final']
                : 'ALTERADA en '.count($cadena['errores']).' punto(s)')), 0, 1);
        }
        if ($firma) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $txt("Firmado electrónicamente por {$firma['firmante']} el ".$firma['firmado_en']->format('Y-m-d H:i:s')
                ." (versión {$firma['version']} del índice)."), 0, 1);
            $pdf->Cell(0, 5, $txt("Código de verificación: {$firma['codigo']}"), 0, 1);
        }
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Ln(3);
        $generado = ($firma['firmado_en'] ?? now())->format('Y-m-d H:i');
        $pdf->Cell(0, 5, $txt('Documento generado automáticamente por el Sistema de Gestión Documental OCOBO el '.$generado.'.'), 0, 1, 'C');

        return $pdf->Output('S');
    }

    /** @param  array{version: int, firmante: string, firmado_en: \DateTimeInterface, codigo: string}|null  $firma */
    public function xml(OfiArchivoExpediente $expediente, ?array $firma = null): string
    {
        $filas = $this->filas($expediente);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><IndiceElectronico/>');
        $exp = $xml->addChild('Expediente');
        $exp->addChild('Numero', htmlspecialchars((string) $expediente->numero_expediente));
        $exp->addChild('Nombre', htmlspecialchars((string) $expediente->nombre_expediente));
        $exp->addChild('Dependencia', htmlspecialchars((string) ($expediente->dependencia_nom ?? $expediente->dependencia?->nom_organico)));
        $exp->addChild('SerieSubserie', htmlspecialchars((string) $expediente->serieTrd?->nom));
        $exp->addChild('Estado', htmlspecialchars((string) $expediente->estado));
        $exp->addChild('FechaApertura', $expediente->fecha_apertura?->format('Y-m-d'));
        $exp->addChild('FechaCierre', $expediente->fecha_cierre?->format('Y-m-d H:i:s'));

        $docs = $xml->addChild('Documentos');
        foreach ($filas as $fila) {
            $d = $docs->addChild('Documento');
            $d->addAttribute('folio', (string) $fila['numero_folio']);
            $d->addAttribute('estado', $fila['activo'] ? 'activo' : 'retirado');
            $d->addChild('FechaIncorporacion', (string) $fila['fecha_incorporacion']);
            $d->addChild('TipoDocumental', htmlspecialchars((string) $fila['tipo_documental']));
            $d->addChild('Origen', htmlspecialchars($fila['origen']));
            $d->addChild('Asunto', htmlspecialchars((string) $fila['asunto']));
            $huella = $d->addChild('Huella', (string) $fila['hash_sha256']);
            $huella->addAttribute('algoritmo', 'SHA-256');
        }

        $integridad = $xml->addChild('Integridad');
        $integridad->addChild('HashIndice', $this->hash($filas))->addAttribute('algoritmo', 'SHA-256');
        if ($expediente->hash_indice) {
            $integridad->addChild('HashSelladoCierre', $expediente->hash_indice);
        }
        $cadena = $this->verificarCadena($expediente);
        if ($cadena['disponible']) {
            $nodo = $integridad->addChild('CadenaIndice', (string) $cadena['hash_final']);
            $nodo->addAttribute('estado', $cadena['integra'] ? 'integra' : 'alterada');
            $nodo->addAttribute('folios', (string) $cadena['total']);
        }
        if ($firma) {
            $f = $xml->addChild('Firma');
            $f->addAttribute('tipo', 'electronica_interna');
            $f->addChild('Version', (string) $firma['version']);
            $f->addChild('Firmante', htmlspecialchars($firma['firmante']));
            $f->addChild('Fecha', $firma['firmado_en']->format('Y-m-d H:i:s'));
            $f->addChild('CodigoVerificacion', $firma['codigo']);
        }

        return $xml->asXML();
    }

    private static function firmar(OfiArchivoExpedienteIndiceVersion $version): string
    {
        $datos = implode('|', [
            $version->expediente_id,
            $version->version,
            $version->hash_indice,
            $version->hash_cadena,
            $version->hash_xml,
            $version->hash_pdf,
            $version->firmado_por,
            $version->firmado_en->format('Y-m-d H:i:s'),
            $version->codigo_verificacion,
        ]);

        return hash_hmac('sha256', $datos, hash_hmac('sha256', 'ocobo-indice-expediente', (string) config('app.key')));
    }

    private static function codigoVerificacion(): string
    {
        return 'IE-'.implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4));
    }

    private function asunto(OfiArchivoExpedienteDocumento $doc): ?string
    {
        return $doc->documentable?->asunto ?? $doc->asunto ?? $doc->detalle;
    }

    /** Hash del folio (documentos cargados) o, si no tiene, el del radicado incorporado. */
    private function hashDocumento(OfiArchivoExpedienteDocumento $doc): ?string
    {
        return $doc->hash_sha256 ?: ($doc->documentable?->hash_sha256 ?: null);
    }

    private function origen(OfiArchivoExpedienteDocumento $doc): string
    {
        $radicado = $doc->documentable?->num_radicado;

        if (str_contains((string) $doc->documentable_type, 'VentanillaRadica')) {
            return 'Radicado: '.($radicado ?? '#'.$doc->documentable_id);
        }

        return $doc->nombre_original ? 'Documento cargado: '.$doc->nombre_original : 'Documento cargado';
    }
}
