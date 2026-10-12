<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;

/**
 * Índice electrónico del expediente (Acuerdo 003 de 2015 AGN).
 *
 * M08-09: el cierre, el PDF y el XML se construyen con las mismas filas, de modo que el
 * `hash_indice` guardado al cerrar se puede recalcular y comparar contra lo que muestra el índice.
 * Los folios retirados se listan (la foliación no se reutiliza) pero marcados como retirados.
 */
class IndiceExpedienteService
{
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
                'asunto' => $doc->documentable?->asunto ?? $doc->asunto ?? $doc->detalle,
                // Hash del folio (documentos cargados) o, si no tiene, el del radicado incorporado
                'hash_sha256' => $doc->hash_sha256 ?: ($doc->documentable?->hash_sha256 ?: null),
                'activo' => (bool) $doc->activo,
            ])
            ->values();
    }

    /** Hash SHA-256 del índice; es el valor que se sella en `hash_indice` al cerrar. */
    public function hash(Collection $filas): string
    {
        return hash('sha256', json_encode($filas->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function pdf(OfiArchivoExpediente $expediente): string
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
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Ln(3);
        $pdf->Cell(0, 5, $txt('Documento generado automáticamente por el Sistema de Gestión Documental OCOBO el '.now()->format('Y-m-d H:i').'.'), 0, 1, 'C');

        return $pdf->Output('S');
    }

    public function xml(OfiArchivoExpediente $expediente): string
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

        return $xml->asXML();
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
