<?php

namespace App\Services;

use App\Helpers\ExportSeguroHelper;
use App\Models\Configuracion\ConfigVarias;
use App\Services\MiBandeja\TempDocumentosRecibidos\LogoEntidadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportesExportService
{
    /**
     * Máximo de filas por exportación síncrona. Sin tope, un export sin filtros cargaba en
     * memoria todos los registros y dejaba el worker ocupado hasta agotar memoria o tiempo.
     * El PDF (DomPDF) es mucho más costoso por fila.
     */
    public const MAX_FILAS = ['excel' => 5000, 'csv' => 5000, 'pdf' => 1000];

    /**
     * Rechaza (422) la exportación si la consulta supera el tope del formato.
     */
    public function validarVolumen(Builder $query, string $formato): void
    {
        $maximo = self::MAX_FILAS[$formato] ?? self::MAX_FILAS['pdf'];
        $total = (clone $query)->count();

        if ($total > $maximo) {
            throw ValidationException::withMessages([
                'format' => "La exportación tiene {$total} registros y el máximo en ".strtoupper($formato)." es {$maximo}. Acote los filtros (por ejemplo, el rango de fechas).",
            ]);
        }
    }

    /** Color primario de la app: cabecera de las tablas y membrete de los archivos. */
    public const COLOR_PRIMARIO = '7367F0';

    /** Ancho máximo de columna en Excel; los textos más largos se ajustan en varias líneas. */
    protected const ANCHO_MAXIMO = 60;

    /** Desde cuántas columnas el PDF y la impresión del Excel salen horizontales para que la tabla quepa. */
    protected const COLUMNAS_HORIZONTAL = 6;

    /**
     * Excel con el membrete de la entidad (logo, razón social, título, fecha y filtros), la cabecera de la
     * tabla con el color de la app, bordes, filas alternas, cabecera fija, autofiltro y listo para imprimir.
     */
    public function exportarExcel(array $data, string $nombre, array $columnas = []): string
    {
        $columnas = $this->columnas($data, $columnas);
        $membrete = $this->membrete();
        $titulo = $data['titulo'] ?? $nombre;
        $ultima = Coordinate::stringFromColumnIndex(max(count($columnas), 1));

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator($membrete['entidad'])->setTitle($titulo);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(str_replace(['\\', '/', '?', '*', ':', '[', ']'], '', $titulo) ?: 'Reporte', 0, 31));
        $sheet->setShowGridlines(false);

        $fila = 1;
        $encabezado = array_filter([
            [$membrete['entidad'], 14, true, 'FFFFFF', self::COLOR_PRIMARIO, 30],
            [$titulo, 12, true, self::COLOR_PRIMARIO, 'EEEDFD', 24],
            [$this->lineaMeta($data, $membrete), 9, false, '444050', 'F8F7FA', 18],
            [$this->lineaResumen($data), 9, false, '444050', 'F8F7FA', 18],
        ], fn ($linea) => $linea[0] !== '');
        foreach ($encabezado as [$texto, $tamano, $negrita, $color, $fondo, $alto]) {
            $estilo = $this->lineaTitulo($sheet, "A{$fila}:{$ultima}{$fila}", $texto);
            $estilo->getFont()->setSize($tamano)->setBold($negrita)->getColor()->setRGB($color);
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
            $sheet->getRowDimension($fila)->setRowHeight($alto);
            $fila++;
        }
        $sheet->getStyle("A1:{$ultima}".($fila - 1))->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_PRIMARIO);
        if (! empty($data['resumen']['Nota'])) {
            $estilo = $this->lineaTitulo($sheet, "A{$fila}:{$ultima}{$fila}", $data['resumen']['Nota']);
            $estilo->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('B26A00');
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF4E5');
            $fila++;
        }
        $this->insertarLogo($sheet, $membrete['logo']);

        $cabecera = $fila + 1;
        $col = 1;
        foreach ($columnas as $etiqueta) {
            ExportSeguroHelper::setCelda($sheet, Coordinate::stringFromColumnIndex($col++).$cabecera, $etiqueta);
        }
        $sheet->getStyle("A{$cabecera}:{$ultima}{$cabecera}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARIO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_PRIMARIO]]],
        ]);
        $sheet->getRowDimension($cabecera)->setRowHeight(24);

        $fila = $cabecera + 1;
        foreach (($data['datos'] ?? []) as $item) {
            $col = 1;
            foreach (array_keys($columnas) as $clave) {
                ExportSeguroHelper::setCelda($sheet, Coordinate::stringFromColumnIndex($col++).$fila, $this->valor($item, $clave));
            }
            if (($fila - $cabecera) % 2 === 0) {
                $sheet->getStyle("A{$fila}:{$ultima}{$fila}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F7FA');
            }
            $fila++;
        }
        $ultimaFila = $fila - 1;

        if ($ultimaFila === $cabecera) {
            $ultimaFila++;
            $this->lineaTitulo($sheet, "A{$ultimaFila}:{$ultima}{$ultimaFila}", 'Sin datos')
                ->getFont()->setItalic(true)->getColor()->setRGB('808390');
        } else {
            $sheet->setAutoFilter("A{$cabecera}:{$ultima}{$ultimaFila}");
        }
        $sheet->getStyle('A'.($cabecera + 1).":{$ultima}{$ultimaFila}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBDADE']]],
        ]);
        $sheet->freezePane('A'.($cabecera + 1));

        $this->ajustarAnchos($sheet, count($columnas), $cabecera, $ultimaFila);
        $this->configurarImpresion($sheet, count($columnas), $cabecera, $membrete['entidad']);

        $path = $this->generarRuta($nombre, 'xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        if (! file_exists($path)) {
            throw new \RuntimeException('No se pudo generar el archivo Excel.');
        }

        return $path;
    }

    /**
     * PDF con el membrete de la entidad en cada página, los filtros como etiquetas, la tabla con el color
     * de la app y el pie con la paginación. Con muchas columnas sale horizontal.
     */
    public function exportarPDF(array $data, string $nombre, ?string $orientacion = null): string
    {
        $columnas = $this->columnas($data);
        $membrete = $this->membrete();
        $logo = $membrete['logo'];

        $pdf = Pdf::loadView('reportes.export-pdf', [
            'titulo' => $data['titulo'] ?? $nombre,
            'entidad' => $membrete['entidad'],
            'meta' => $this->lineaMeta($data, $membrete),
            'logo' => $logo ? 'data:'.$logo['mime'].';base64,'.base64_encode($logo['bytes']) : null,
            'columnas' => array_values($columnas),
            'datos' => array_map(
                fn ($item) => array_map(fn ($clave) => $this->valor($item, $clave), array_keys($columnas)),
                $data['datos'] ?? []
            ),
            'resumen' => $this->resumen($data),
            'nota' => $data['resumen']['Nota'] ?? null,
            'color' => '#'.self::COLOR_PRIMARIO,
        ]);

        $pdf->setPaper('letter', $orientacion ?? (count($columnas) > self::COLUMNAS_HORIZONTAL ? 'landscape' : 'portrait'));
        // Sin subsetting la fuente completa pesaba ~800 KB en cada reporte
        $pdf->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);
        $pdf->render();
        $this->numerarPaginas($pdf->getDomPDF());

        $path = $this->generarRuta($nombre, 'pdf');
        $bytes = file_put_contents($path, $pdf->output());

        if ($bytes === false) {
            throw new \RuntimeException('No se pudo generar el archivo PDF.');
        }

        return $path;
    }

    /** "Página N de M" a la derecha del pie; dompdf no resuelve counter(pages) en CSS. */
    protected function numerarPaginas(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $metricas = $dompdf->getFontMetrics();
        $fuente = $metricas->getFont('DejaVu Sans');
        $ancho = $metricas->getTextWidth('Página 00 de 00', $fuente, 7);

        $canvas->page_text($canvas->get_width() - 24 - $ancho, $canvas->get_height() - 26,
            'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 7, [0.5, 0.51, 0.56]);
    }

    /**
     * Columnas a exportar como [clave del dato => encabezado]. Los módulos que no mandan columnas usan las
     * claves de la primera fila (num_radicado → "Num radicado"); sin esto el PDF salía con la tabla vacía.
     *
     * @return array<string, string>
     */
    protected function columnas(array $data, array $columnas = []): array
    {
        $claves = $columnas ?: (($data['columnas'] ?? []) ?: array_keys($data['datos'][0] ?? []));

        return collect($claves)->mapWithKeys(fn ($clave) => [(string) $clave => self::etiqueta((string) $clave)])->all();
    }

    protected static function etiqueta(string $clave): string
    {
        return preg_match('/^[a-z0-9_]+$/', $clave) ? Str::ucfirst(str_replace('_', ' ', $clave)) : $clave;
    }

    protected function valor(mixed $item, string $clave): mixed
    {
        $valor = is_array($item) ? ($item[$clave] ?? $item[strtolower($clave)] ?? '') : '';

        return is_array($valor) ? implode(', ', $valor) : $valor;
    }

    /** @return array{entidad: string, nit: ?string, logo: ?array} */
    protected function membrete(): array
    {
        $empresa = ConfigVarias::getInformacionEmpresa();

        return [
            'entidad' => ($empresa['razon_social_empresa'] ?? null) ?: (string) config('app.name'),
            'nit' => ($empresa['nit_empresa'] ?? null) ?: null,
            'logo' => app(LogoEntidadService::class)->archivo(),
        ];
    }

    protected function lineaMeta(array $data, array $membrete): string
    {
        return implode('   ·   ', array_filter([
            $membrete['nit'] ? 'NIT '.$membrete['nit'] : null,
            'Generado: '.($data['fecha_generacion'] ?? now()->format('Y-m-d H:i:s')),
            'Total registros: '.($data['total'] ?? count($data['datos'] ?? [])),
        ]));
    }

    /**
     * Filtros y totales del reporte para mostrar bajo el título; sin el total, que va en la línea de
     * metadatos, ni la nota, que se destaca aparte.
     *
     * @return array<string, mixed>
     */
    protected function resumen(array $data): array
    {
        return collect($data['resumen'] ?? [])
            ->reject(fn ($valor, $clave) => is_array($valor) || in_array(strtolower((string) $clave), ['total', 'nota'], true))
            ->mapWithKeys(fn ($valor, $clave) => [self::etiqueta((string) $clave) => $valor])
            ->all();
    }

    protected function lineaResumen(array $data): string
    {
        return collect($this->resumen($data))->map(fn ($valor, $clave) => "{$clave}: {$valor}")->implode('   ·   ');
    }

    protected function lineaTitulo(Worksheet $sheet, string $rango, string $texto): Style
    {
        [$desde, $hasta] = explode(':', $rango);
        ExportSeguroHelper::setCelda($sheet, $desde, $texto);
        if ($desde !== $hasta) {
            $sheet->mergeCells($rango);
        }
        $sheet->getStyle($rango)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        return $sheet->getStyle($rango);
    }

    /** El logo va arriba a la izquierda, a la altura del membrete. */
    protected function insertarLogo(Worksheet $sheet, ?array $logo): void
    {
        $imagen = $logo ? @imagecreatefromstring($logo['bytes']) : false;
        if (! $imagen) {
            return;
        }

        $dibujo = new MemoryDrawing;
        $dibujo->setName('Logo')->setImageResource($imagen);
        $dibujo->setRenderingFunction(MemoryDrawing::RENDERING_PNG)->setMimeType(MemoryDrawing::MIMETYPE_PNG);
        $dibujo->setCoordinates('A1')->setOffsetX(4)->setOffsetY(4)->setHeight(52)->setWorksheet($sheet);
    }

    /** Ancho según el contenido de la tabla (el membrete combinado no cuenta), con tope y ajuste de línea. */
    protected function ajustarAnchos(Worksheet $sheet, int $columnas, int $desde, int $hasta): void
    {
        for ($i = 1; $i <= $columnas; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $largo = 0;
            for ($fila = $desde; $fila <= $hasta; $fila++) {
                $largo = max($largo, mb_strlen((string) $sheet->getCell($col.$fila)->getValue()));
            }
            $sheet->getColumnDimension($col)->setWidth(min(max($largo + 3, 10), self::ANCHO_MAXIMO));
            if ($largo + 3 > self::ANCHO_MAXIMO) {
                $sheet->getStyle($col.($desde + 1).':'.$col.$hasta)->getAlignment()->setWrapText(true);
            }
        }
    }

    protected function configurarImpresion(Worksheet $sheet, int $columnas, int $cabecera, string $entidad): void
    {
        $sheet->getPageSetup()
            ->setOrientation($columnas > self::COLUMNAS_HORIZONTAL ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($cabecera, $cabecera);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.str_replace('&', '&&', $entidad).'&R&8Página &P de &N');
    }

    /**
     * Stream CSV to response.
     */
    public function exportarCSV(array $data, string $nombre, array $columnas = [])
    {
        $headers = $columnas ?: array_keys(($data['datos'][0] ?? []));
        $sanitizado = self::sanitizarNombre($nombre);

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            $this->escribirCSV($handle, $data, $headers);
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$sanitizado.'_'.date('Ymd_His').'.csv"',
        ]);
    }

    /**
     * Guarda el CSV en disco y devuelve la ruta (reportes programados: se adjunta al correo).
     */
    public function guardarCSV(array $data, string $nombre, array $columnas = []): string
    {
        $headers = $columnas ?: array_keys(($data['datos'][0] ?? []));
        $path = $this->generarRuta($nombre, 'csv');

        $handle = fopen($path, 'w');
        $this->escribirCSV($handle, $data, $headers);
        fclose($handle);

        return $path;
    }

    /** @param resource $handle */
    protected function escribirCSV($handle, array $data, array $headers): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        foreach (($data['datos'] ?? []) as $item) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = is_array($item) ? ($item[$header] ?? $item[strtolower($header)] ?? '') : '';
            }
            fputcsv($handle, ExportSeguroHelper::filaCsv($row));
        }
    }

    protected function generarRuta(string $nombre, string $extension): string
    {
        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $sanitizado = self::sanitizarNombre($nombre);

        return $dir.'/'.$sanitizado.'_'.date('Ymd_His').'.'.$extension;
    }

    protected static function sanitizarNombre(string $nombre): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $nombre);
    }
}
