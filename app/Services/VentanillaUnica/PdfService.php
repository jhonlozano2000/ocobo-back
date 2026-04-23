<?php

namespace App\Services\VentanillaUnica;

use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Servicio para generación de documentos PDF
 * Soporta PDF/A-1b para archivo a largo plazo
 */
class PdfService
{
    /**
     * Genera un PDF simple (sin PDF/A)
     */
    public function generate(string $view, array $data = [], array $options = [])
    {
        $pdf = Pdf::loadView($view, $data);
        
        if (isset($options['orientation'])) {
            $pdf->setPaper('a4', $options['orientation']);
        }
        
        if (isset($options['filename'])) {
            $pdf->filename = $options['filename'];
        }
        
        return $pdf;
    }

    /**
     * Genera un PDF/A-1b para archivo a largo plazo
     * 
     * @param string $view Vista de Blade para el contenido
     * @param array $data Datos para la vista
     * @param array $options Opciones adicionales (orientation, filename, metadata)
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePdfA(string $view, array $data = [], array $options = [])
    {
        $defaultMetadata = [
            'Title' => 'Documento OCOBO',
            'Author' => config('app.name', 'OCOBO'),
            'Creator' => config('app.name', 'OCOBO') . ' - Sistema de Gestión Documental',
            'Producer' => 'DomPDF',
            'CreationDate' => now()->format('Y-m-d\TH:i:s+00:00'),
        ];

        $metadata = array_merge($defaultMetadata, $options['metadata'] ?? []);
        
        $pdf = Pdf::loadView($view, $data);
        
        $pdf->setPaper('a4', $options['orientation'] ?? 'portrait');
        
        $pdf->getDomPDF()->set_option('isRemoteEnabled', true);
        
        $pdf->getDomPDF()->get_canvas()->page_text(
            0, 0, 
            "Página {PAGE_NUM} de {PAGE_COUNT}", 
            $pdf->getDomPDF()->get_font_metrics()->get_font('Helvetica', 'regular'), 
            8, 
            [128, 128, 128]
        );

        $dompdf = $pdf->getDomPDF();
        
        $dompdf->add_info('Title', $metadata['Title']);
        $dompdf->add_info('Author', $metadata['Author']);
        $dompdf->add_info('Creator', $metadata['Creator']);
        $dompdf->add_info('Producer', $metadata['Producer']);
        
        if (isset($options['filename'])) {
            $pdf->filename = $options['filename'];
        } else {
            $pdf->filename = $metadata['Title'] . '.pdf';
        }
        
        return $pdf;
    }

    /**
     * Genera rótulo de radicado en PDF
     */
    public function generateRotulo(array $radicado, array $options = [])
    {
        $isPdfA = $options['pdf_a'] ?? false;
        
        $data = [
            'radicado' => $radicado,
            'entidad' => config('app.name', 'Entidad'),
            'nit' => config('app.nit', 'NIT'),
            'fecha' => $radicado['fec_radi'] ?? now()->format('Y-m-d'),
            'hora' => $radicado['hor_radi'] ?? now()->format('H:i:s'),
        ];

        if ($isPdfA) {
            return $this->generatePdfA('pdf.rotulo', $data, [
                'orientation' => 'landscape',
                'metadata' => [
                    'Title' => 'Rótulo - ' . ($radicado['num_radicado'] ?? ''),
                ],
                'filename' => 'rotulo-' . ($radicado['num_radicado'] ?? date('YmdHis')) . '.pdf',
            ]);
        }

        return $this->generate('pdf.rotulo', $data, [
            'orientation' => 'landscape',
            'filename' => 'rotulo-' . ($radicado['num_radicado'] ?? date('YmdHis')) . '.pdf',
        ]);
    }

    /**
     * Genera reporte de radicación en PDF
     */
    public function generateReporte(array $radicado, array $options = [])
    {
        $isPdfA = $options['pdf_a'] ?? false;
        
        $data = [
            'radicado' => $radicado,
            'entidad' => config('app.name', 'Entidad'),
            'nit' => config('app.nit', 'NIT'),
            'fechaGeneracion' => now()->format('Y-m-d H:i:s'),
        ];

        if ($isPdfA) {
            return $this->generatePdfA('pdf.reporte', $data, [
                'orientation' => 'portrait',
                'metadata' => [
                    'Title' => 'Reporte Radicación - ' . ($radicado['num_radicado'] ?? ''),
                ],
                'filename' => 'reporte-' . ($radicado['num_radicado'] ?? date('YmdHis')) . '.pdf',
            ]);
        }

        return $this->generate('pdf.reporte', $data, [
            'filename' => 'reporte-' . ($radicado['num_radicado'] ?? date('YmdHis')) . '.pdf',
        ]);
    }

    /**
     * Genera constancia de recibido en PDF
     */
    public function generateConstancia(array $radicado, array $options = [])
    {
        $isPdfA = $options['pdf_a'] ?? false;
        
        $data = [
            'radicado' => $radicado,
            'entidad' => config('app.name', 'Entidad'),
            'nit' => config('app.nit', 'NIT'),
            'fechaConstancia' => now()->format('Y-m-d H:i:s'),
        ];

        if ($isPdfA) {
            return $this->generatePdfA('pdf.constancia', $data, [
                'orientation' => 'portrait',
                'metadata' => [
                    'Title' => 'Constancia de Recibido - ' . ($radicado['num_radicado'] ?? ''),
                ],
                'filename' => 'constancia-' . ($radicado['num_radicado'] ?? date('YmdHis')) . '.pdf',
            ]);
        }

        return $this->generate('pdf.constancia', $data, [
            'filename' => 'constancia-' . ($radicado['num_radicado'] ?? date('YmdHis')) . '.pdf',
        ]);
    }

    /**
     * Descarga el PDF generado
     */
    public function download($pdf, string $filename = null)
    {
        if ($filename) {
            return $pdf->download($filename);
        }
        
        return $pdf->download();
    }

    /**
     * Stream del PDF al navegador
     */
    public function stream($pdf, string $filename = null)
    {
        if ($filename) {
            return $pdf->stream($filename);
        }
        
        return $pdf->stream();
    }

    /**
     * Determina si un documento debe generarse en PDF/A
     * basado en su clasificación
     */
    public function shouldUsePdfA(?string $clasificacion): bool
    {
        if (!$clasificacion) {
            return false;
        }

        $clasificacionesPdfA = [
            'CONFIDENCIAL',
            'RESERVADO',
            'OFICIAL',
            'LEGAL',
        ];

        return in_array(strtoupper($clasificacion), $clasificacionesPdfA);
    }
}