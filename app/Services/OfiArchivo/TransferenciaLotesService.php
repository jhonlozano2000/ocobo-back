<?php

namespace App\Services\OfiArchivo;

use App\Helpers\ExportSeguroHelper;
use App\Models\Configuracion\ConfigVarias;
use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Models\OfiArchivo\OfiArchivoTransferenciaLote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use setasign\Fpdi\Fpdi;

/**
 * B17: transferencias documentales por lote (diseño de expedientes §3.7; Acuerdo AGN 001/2024
 * A.4.4.3 y Anexo 3).
 *
 * - Envío: se validan todos los expedientes del lote, se genera el FUID (XLSX) y quien envía lo
 *   firma; los expedientes quedan 'en_transferencia'.
 * - Recepción: quien recibe (otra persona, con el permiso Recibir) coteja cada expediente. Si todos
 *   son conformes, los expedientes pasan al archivo de destino y se genera el acta de entrega con
 *   las dos firmas; si alguno no lo es, el lote se devuelve con observaciones y los expedientes
 *   vuelven cerrados al archivo de origen.
 *
 * La firma es la electrónica interna (D2): HMAC con una llave derivada de la de la aplicación.
 */
class TransferenciaLotesService
{
    public const PERMISO_RECIBIR = 'Gestion de Archivo -> Transferencias -> Recibir';

    public function __construct(
        private IndiceExpedienteService $indice,
        private HojaControlService $hojaControl,
        private UbicacionTopograficaService $ubicaciones,
    ) {}

    /**
     * Crea y envía el lote. Lanza ValidationException con el motivo de cada expediente que no
     * puede transferirse.
     *
     * @param  array{tipo: string, dependencia_id: int, origen: string, destino: string, expediente_ids: list<int>, observaciones?: ?string}  $datos
     */
    public function enviar(array $datos, User $envia): OfiArchivoTransferenciaLote
    {
        return DB::transaction(function () use ($datos, $envia) {
            $expedientes = OfiArchivoExpediente::with(['serieTrd', 'subserieTrd'])
                ->whereIn('id', $datos['expediente_ids'])->orderBy('id')->lockForUpdate()->get();

            $errores = [];
            foreach ($expedientes as $expediente) {
                $motivo = (int) $expediente->dependencia_id !== (int) $datos['dependencia_id']
                    ? 'No pertenece a la dependencia del lote'
                    : $this->validarExpediente($expediente, $datos['tipo']);
                if ($motivo) {
                    $errores[] = "{$expediente->numero_expediente}: {$motivo}";
                }
            }
            if ($errores !== []) {
                throw ValidationException::withMessages(['expediente_ids' => $errores]);
            }

            $fecha = now()->startOfSecond();
            $lote = new OfiArchivoTransferenciaLote([
                'codigo' => $this->siguienteCodigo($datos['tipo'], $fecha),
                'tipo' => $datos['tipo'],
                'dependencia_id' => $datos['dependencia_id'],
                'dependencia_cod' => $expedientes->first()->dependencia_cod,
                'dependencia_nom' => $expedientes->first()->dependencia_nom,
                'origen' => $datos['origen'],
                'destino' => $datos['destino'],
                'estado' => 'enviado',
                'observaciones' => $datos['observaciones'] ?? null,
                'usuario_envia_id' => $envia->id,
                'firmante_envio' => self::nombre($envia),
                'fecha_envio' => $fecha,
                'codigo_verificacion' => 'TD-'.implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4)),
            ]);
            $lote->fuid = $this->fuid($lote, $expedientes);
            $lote->hash_fuid = hash('sha256', $lote->fuid);
            $lote->firma_envio = self::firmar($lote, 'envio');
            $lote->save();

            foreach ($expedientes as $expediente) {
                OfiArchivoTransferencia::create([
                    'lote_id' => $lote->id,
                    'expediente_id' => $expediente->id,
                    'tipo' => $lote->tipo,
                    'origen' => $lote->origen,
                    'destino' => $lote->destino,
                    'responsable_origen_id' => $envia->id,
                    'fecha_transferencia' => $fecha,
                    'estado' => 'pendiente',
                    'usuario_registro_id' => $envia->id,
                ]);
                $expediente->update(['estado' => 'en_transferencia']);
            }

            return $lote;
        });
    }

    /**
     * Cotejo de la recepción: un resultado por cada expediente del lote.
     *
     * @param  array<int, array{conforme: bool, observacion?: ?string}>  $cotejo  por id del detalle
     */
    public function recibir(OfiArchivoTransferenciaLote $lote, array $cotejo, ?string $observaciones, User $recibe): OfiArchivoTransferenciaLote
    {
        return DB::transaction(function () use ($lote, $cotejo, $observaciones, $recibe) {
            $lote = OfiArchivoTransferenciaLote::lockForUpdate()->findOrFail($lote->id);

            if ($lote->estado !== 'enviado') {
                throw ValidationException::withMessages(['lote' => "El lote ya fue {$lote->estado}"]);
            }
            if ((int) $lote->usuario_envia_id === (int) $recibe->id) {
                throw ValidationException::withMessages(['lote' => 'Quien envió el lote no puede recibirlo']);
            }

            $items = $lote->items()->with(['expediente.serieTrd', 'expediente.subserieTrd'])->get();
            $faltan = $items->pluck('id')->diff(array_keys($cotejo));
            if ($faltan->isNotEmpty() || count($cotejo) !== $items->count()) {
                throw ValidationException::withMessages(['items' => 'El cotejo debe incluir cada expediente del lote, y solo esos']);
            }

            $noConformes = $items->filter(fn ($item) => ! $cotejo[$item->id]['conforme']);
            foreach ($noConformes as $item) {
                if (trim((string) ($cotejo[$item->id]['observacion'] ?? '')) === '') {
                    throw ValidationException::withMessages(['items' => "Indique por qué el expediente {$item->expediente->numero_expediente} no es conforme"]);
                }
            }
            $recibido = $noConformes->isEmpty();
            if (! $recibido && trim((string) $observaciones) === '') {
                throw ValidationException::withMessages(['observaciones' => 'Las observaciones son obligatorias al devolver el lote']);
            }

            foreach ($items as $item) {
                $item->update([
                    'estado' => $recibido ? 'aprobada' : 'rechazada',
                    'cotejo' => $cotejo[$item->id]['conforme'] ? 'conforme' : 'no_conforme',
                    'observacion_cotejo' => $cotejo[$item->id]['observacion'] ?? null,
                    'responsable_destino_id' => $recibe->id,
                ]);
                $item->expediente->update(match (true) {
                    ! $recibido => ['estado' => 'cerrado'],
                    $lote->tipo === 'primaria' => ['estado' => 'cerrado', 'fase' => 'central'],
                    default => ['estado' => 'transferido', 'fase' => 'historico'],
                });
            }

            $lote->fill([
                'estado' => $recibido ? 'recibido' : 'devuelto',
                'observaciones_recepcion' => $observaciones,
                'usuario_recibe_id' => $recibe->id,
                'firmante_recepcion' => self::nombre($recibe),
                'fecha_recepcion' => now()->startOfSecond(),
            ]);
            if ($recibido) {
                $lote->acta = $this->acta($lote, $items);
                $lote->hash_acta = hash('sha256', $lote->acta);
                $lote->firma_recepcion = self::firmar($lote, 'recepcion');
            }
            $lote->save();

            $this->notificar($lote);

            return $lote;
        });
    }

    /**
     * Motivo por el que el expediente no puede ir en un lote de este tipo; null si puede.
     */
    public function validarExpediente(OfiArchivoExpediente $expediente, string $tipo): ?string
    {
        if (OfiArchivoTransferencia::where('expediente_id', $expediente->id)->where('estado', 'pendiente')->exists()) {
            return 'Ya tiene una transferencia pendiente';
        }

        $trd = $expediente->trdRetencion();
        if (! $trd) {
            return 'No tiene serie/subserie TRD asociada';
        }

        if ($tipo === 'primaria') {
            if ($expediente->estado !== 'cerrado' || $expediente->fase !== 'gestion') {
                return 'Solo se transfieren al archivo central expedientes cerrados en el archivo de gestión';
            }
        } else {
            if ($expediente->estado !== 'cerrado' || $expediente->fase !== 'central') {
                return 'La transferencia secundaria exige que el expediente esté cerrado en el archivo central';
            }
            if (! $trd->ct && ! $trd->s) {
                return 'La disposición final de la TRD no es conservación total ni selección';
            }
        }

        if (OfiArchivoPrestamo::where('expediente_id', $expediente->id)->whereIn('estado', ['prestado', 'vencido'])->exists()) {
            return 'Tiene préstamos sin devolver';
        }

        // El índice firmado al cierre llega con F1; la secundaria valida su paquete de preservación en F5
        if ($tipo === 'primaria' && IndiceExpedienteService::disponible()) {
            $version = OfiArchivoExpedienteIndiceVersion::where('expediente_id', $expediente->id)->latest('version')->first();
            if (! $version) {
                return 'No tiene índice electrónico firmado; reábralo y ciérrelo de nuevo para firmarlo';
            }
            if (! ($verificacion = $this->indice->verificarVersion($version))['valida']) {
                return "Su índice firmado no supera la verificación ({$verificacion['motivo']})";
            }
        }

        if ($expediente->soporte !== 'electronico') {
            $faltan = array_keys(array_filter([
                'ubicación' => blank($expediente->ubicacion_fisica),
                'caja' => blank($expediente->caja),
                'carpeta' => blank($expediente->carpeta),
            ]));
            if ($faltan !== []) {
                return 'Al ser '.($expediente->soporte === 'fisico' ? 'físico' : 'híbrido').' le falta registrar: '.implode(', ', $faltan);
            }

            // B20: con el catálogo activo, el expediente y sus tomos están en cajas del archivo de su fase
            if (ConfiguracionArchivo::ubicacionConCatalogo() && UbicacionTopograficaService::disponible()) {
                $tomos = HojaControlService::disponible() ? $this->hojaControl->tomos($expediente) : collect();
                if ($motivo = $this->ubicaciones->motivoSinUbicar($expediente, $tomos)) {
                    return $motivo;
                }
            }

            // B19: cada tomo se entrega en su caja y carpeta; la hoja se firmó al cierre
            if (HojaControlService::disponible()) {
                $sinUbicar = $this->hojaControl->tomos($expediente)->filter(fn ($t) => blank($t['caja']) || blank($t['carpeta']))->pluck('numero');
                if ($sinUbicar->isNotEmpty()) {
                    return 'Le falta registrar la caja o la carpeta del tomo '.$sinUbicar->implode(', ');
                }
                if ($tipo === 'primaria') {
                    $hoja = $this->hojaControl->ultimaVersion($expediente);
                    if (! $hoja) {
                        return 'No tiene hoja de control firmada; reábralo y ciérrelo de nuevo para firmarla';
                    }
                    if (! ($verificacion = $this->hojaControl->verificarVersion($hoja, $expediente))['valida']) {
                        return "Su hoja de control firmada no supera la verificación ({$verificacion['motivo']})";
                    }
                }
            }
        }

        return $tipo === 'primaria'
            ? self::validarRetencion($expediente, (int) $trd->a_g, 'en el archivo de gestión')
            : self::validarRetencion($expediente, (int) $trd->a_g + (int) $trd->a_c, 'total (gestión + central)');
    }

    public static function validarRetencion(OfiArchivoExpediente $expediente, int $anios, string $etapa): ?string
    {
        if (! $expediente->fecha_cierre) {
            return 'El expediente no tiene fecha de cierre';
        }

        $cumple = Carbon::parse($expediente->fecha_cierre)->addYears($anios);
        if ($cumple->isFuture()) {
            return "No se ha cumplido el tiempo de retención {$etapa} ({$anios} años desde el cierre; se cumple el {$cumple->format('Y-m-d')})";
        }

        return null;
    }

    /**
     * Comprueba que el FUID y el acta guardados son los que se firmaron.
     *
     * @return array{integro: bool, problemas: list<string>}
     */
    public function verificar(OfiArchivoTransferenciaLote $lote): array
    {
        $problemas = [];
        if (! hash_equals($lote->hash_fuid, hash('sha256', (string) $lote->fuid))) {
            $problemas[] = 'El FUID no coincide con su huella';
        }
        if (! hash_equals($lote->firma_envio, self::firmar($lote, 'envio'))) {
            $problemas[] = 'La firma de envío no corresponde al lote';
        }
        if ($lote->estado === 'recibido') {
            if (! hash_equals((string) $lote->hash_acta, hash('sha256', (string) $lote->acta))) {
                $problemas[] = 'El acta no coincide con su huella';
            }
            if (! hash_equals((string) $lote->firma_recepcion, self::firmar($lote, 'recepcion'))) {
                $problemas[] = 'La firma de recepción no corresponde al lote';
            }
        }

        return ['integro' => $problemas === [], 'problemas' => $problemas];
    }

    /**
     * FUID (Acuerdo AGN 001/2024, Anexo 3) con el bloque de los documentos electrónicos.
     *
     * @param  Collection<int, OfiArchivoExpediente>  $expedientes
     */
    public function fuid(OfiArchivoTransferenciaLote $lote, Collection $expedientes): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('FUID');
        $celda = fn (string $c, mixed $v) => ExportSeguroHelper::setCelda($hoja, $c, $v);
        $entidad = self::entidad();

        $hoja->mergeCells('A1:R1');
        $celda('A1', 'FORMATO ÚNICO DE INVENTARIO DOCUMENTAL (FUID)');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $encabezado = [
            ['ENTIDAD REMITENTE', $entidad],
            ['ENTIDAD PRODUCTORA', $entidad],
            ['UNIDAD ADMINISTRATIVA', $lote->dependencia_nom],
            ['OFICINA PRODUCTORA', trim($lote->dependencia_cod.' '.$lote->dependencia_nom)],
            ['OBJETO', $lote->tipo === 'primaria' ? 'Transferencia primaria' : 'Transferencia secundaria'],
        ];
        foreach ($encabezado as $i => [$campo, $valor]) {
            $fila = $i + 3;
            $celda("A{$fila}", $campo);
            $hoja->mergeCells("B{$fila}:H{$fila}");
            $celda("B{$fila}", (string) $valor);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
        }

        $celda('M3', 'REGISTRO DE ENTRADA');
        $hoja->getStyle('M3')->getFont()->setBold(true);
        foreach (['M' => ['AÑO', $lote->fecha_envio->year], 'N' => ['MES', $lote->fecha_envio->month], 'O' => ['DÍA', $lote->fecha_envio->day], 'P' => ['N.T.', $lote->codigo]] as $col => [$campo, $valor]) {
            $celda("{$col}4", $campo);
            $celda("{$col}5", $valor);
        }

        $columnas = [
            'N.º de orden', 'Código', 'Nombre de las series, subseries o asuntos', 'Fecha inicial', 'Fecha final',
            'Caja', 'Carpeta', 'Tomo', 'Otro', 'N.º de folios', 'Soporte', 'Frecuencia de consulta', 'Notas',
            'Ubicación (electrónico)', 'Cantidad de documentos (electrónico)', 'Tamaño en bytes (electrónico)',
            'Índice firmado', 'Hash del índice (SHA-256)',
        ];
        $filaEncabezado = 10;
        foreach ($columnas as $i => $titulo) {
            $celda(chr(65 + $i).$filaEncabezado, $titulo);
        }
        $rango = "A{$filaEncabezado}:R{$filaEncabezado}";
        $hoja->getStyle($rango)->getFont()->setBold(true);
        $hoja->getStyle($rango)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getStyle($rango)->getFill()->setFillType('solid')->getStartColor()->setRGB('E6E6E6');

        $fila = $filaEncabezado;
        foreach ($expedientes->values() as $orden => $expediente) {
            $fila++;
            $datos = $this->datosInventario($expediente);
            $valores = [
                $orden + 1, $datos['codigo'], $datos['nombre'], $datos['fecha_inicial'], $datos['fecha_final'],
                $datos['caja'], $datos['carpeta'], $datos['tomos'], '', $datos['folios'], $datos['soporte'],
                $datos['frecuencia'], $datos['notas'], $datos['ubicacion_electronica'], $datos['documentos_electronicos'],
                $datos['tamano'], $datos['indice_codigo'], (string) $expediente->hash_indice,
            ];
            foreach ($valores as $i => $valor) {
                $celda(chr(65 + $i).$fila, $valor);
            }
        }
        $hoja->getStyle("A{$filaEncabezado}:R{$fila}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $fila += 2;
        foreach ([
            ['Elaborado y entregado por', "{$lote->firmante_envio} — {$lote->fecha_envio->format('Y-m-d H:i:s')}"],
            ['Lugar', $lote->origen],
            ['Recibido por', 'Se registra en el acta de entrega del lote '.$lote->codigo],
            ['Código de verificación', $lote->codigo_verificacion],
        ] as [$campo, $valor]) {
            $celda("A{$fila}", $campo);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
            $hoja->mergeCells("B{$fila}:H{$fila}");
            $celda("B{$fila}", $valor);
            $fila++;
        }

        foreach (['A' => 18, 'B' => 18, 'C' => 45, 'M' => 30, 'N' => 30, 'R' => 66] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        // El archivo debe ser idéntico al firmado: sin fechas de creación variables fuera de las del lote
        $libro->getProperties()->setCreator('SGDEA Ocobo')->setCreated($lote->fecha_envio->getTimestamp())
            ->setModified($lote->fecha_envio->getTimestamp())->setTitle('FUID '.$lote->codigo);

        $ruta = tempnam(sys_get_temp_dir(), 'fuid');
        try {
            IOFactory::createWriter($libro, 'Xlsx')->save($ruta);

            return file_get_contents($ruta);
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * Acta de entrega (A.4.4.3 par. 4–5): oficina, series y subseries, unidades, TRD aplicada,
     * repositorio, medios, estructura lógica, tamaño, cotejo y las firmas de las dos partes.
     *
     * @param  Collection<int, OfiArchivoTransferencia>  $items
     */
    public function acta(OfiArchivoTransferenciaLote $lote, Collection $items): string
    {
        $txt = fn (?string $s) => mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');
        $inventario = $items->map(fn (OfiArchivoTransferencia $item) => $this->datosInventario($item->expediente) + ['item' => $item]);

        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->MultiCell(0, 7, $txt('ACTA DE ENTREGA DE TRANSFERENCIA DOCUMENTAL '.strtoupper($lote->tipo === 'primaria' ? 'primaria' : 'secundaria')), 0, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $txt(self::entidad()), 0, 1, 'C');
        $pdf->Ln(3);

        $series = $inventario->groupBy('serie')->map->count();
        $trd = $inventario->pluck('trd_version')->filter()->unique()->implode(', ');
        $soportes = $inventario->pluck('soporte')->unique()->implode(', ');
        $datos = [
            'Lote' => $lote->codigo,
            'Fecha de envío' => $lote->fecha_envio->format('Y-m-d H:i:s'),
            'Fecha de recepción' => $lote->fecha_recepcion->format('Y-m-d H:i:s'),
            'Oficina productora' => trim($lote->dependencia_cod.' '.$lote->dependencia_nom),
            'Origen' => $lote->origen,
            'Destino (repositorio)' => $lote->destino,
            'Series y subseries' => $series->map(fn ($n, $serie) => "{$serie} ({$n})")->implode('; '),
            'Unidades documentales' => $items->count().' expediente(s), '.$inventario->sum('folios').' folio(s)',
            'TRD aplicada' => $trd !== '' ? "Versión {$trd}" : 'Instantánea tomada al cierre de cada expediente',
            'Medios' => $soportes,
            'Estructura lógica' => 'Expediente con sus documentos en orden de foliación e índice electrónico firmado (XML y PDF)',
            'Documentos electrónicos' => $inventario->sum('documentos_electronicos').' documento(s), '.number_format($inventario->sum('tamano'), 0, ',', '.').' bytes',
            'FUID (SHA-256)' => $lote->hash_fuid,
        ];
        foreach ($datos as $campo => $valor) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(48, 6, $txt($campo.':'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 6, $txt($valor), 0, 'L');
        }

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        foreach ([[10, 'N.º'], [36, 'Expediente'], [64, 'Serie / nombre'], [16, 'Folios'], [22, 'Soporte'], [42, 'Cotejo']] as [$ancho, $titulo]) {
            $pdf->Cell($ancho, 7, $txt($titulo), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 7.5);
        foreach ($inventario->values() as $i => $fila) {
            $pdf->Cell(10, 7, (string) ($i + 1), 1, 0, 'C');
            $pdf->Cell(36, 7, $txt(mb_substr($fila['item']->expediente->numero_expediente, 0, 24)), 1, 0);
            $pdf->Cell(64, 7, $txt(mb_substr($fila['nombre'], 0, 44)), 1, 0);
            $pdf->Cell(16, 7, (string) $fila['folios'], 1, 0, 'C');
            $pdf->Cell(22, 7, $txt($fila['soporte']), 1, 0, 'C');
            $pdf->Cell(42, 7, $txt('Conforme'), 1, 1, 'C');
        }

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(95, 6, $txt('Entrega'), 0, 0);
        $pdf->Cell(95, 6, $txt('Recibe'), 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(95, 6, $txt($lote->firmante_envio), 0, 0);
        $pdf->Cell(95, 6, $txt($lote->firmante_recepcion), 0, 1);
        $pdf->Cell(95, 6, $txt('Firmado electrónicamente el '.$lote->fecha_envio->format('Y-m-d H:i:s')), 0, 0);
        $pdf->Cell(95, 6, $txt('Firmado electrónicamente el '.$lote->fecha_recepcion->format('Y-m-d H:i:s')), 0, 1);
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, $txt("Código de verificación: {$lote->codigo_verificacion}"), 0, 1);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, $txt('Documento generado automáticamente por el Sistema de Gestión Documental OCOBO.'), 0, 1, 'C');

        return $pdf->Output('S');
    }

    /** @return array<string, mixed> */
    private function datosInventario(OfiArchivoExpediente $expediente): array
    {
        $documentos = $expediente->documentos()->where('activo', true)->get(['fecha_documento', 'fecha_incorporacion', 'tamano_bytes', 'archivo_path', 'hash_sha256']);
        $fechas = $documentos->map(fn ($d) => $d->fecha_documento ? substr((string) $d->fecha_documento, 0, 10) : $d->fecha_incorporacion?->format('Y-m-d'))->filter()->sort()->values();
        $electronicos = $documentos->filter(fn ($d) => $d->archivo_path || $d->hash_sha256);
        $serie = $expediente->subserieTrd ?? $expediente->serieTrd;
        $prestamos = OfiArchivoPrestamo::where('expediente_id', $expediente->id)->count();
        $tomos = HojaControlService::disponible() && $expediente->soporte !== 'electronico' ? $this->hojaControl->tomos($expediente) : collect();
        $version = IndiceExpedienteService::disponible()
            ? OfiArchivoExpedienteIndiceVersion::where('expediente_id', $expediente->id)->latest('version')->value('codigo_verificacion')
            : null;

        return [
            'codigo' => implode('.', array_filter([$expediente->dependencia_cod, $expediente->serieTrd?->cod, $expediente->subserieTrd?->cod])),
            'serie' => trim(($serie?->cod ?? '').' '.($serie?->nom ?? 'Sin serie')),
            'nombre' => trim(($serie?->nom ? $serie->nom.' — ' : '').$expediente->nombre_expediente),
            'fecha_inicial' => $fechas->first() ?? $expediente->fecha_apertura?->format('Y-m-d'),
            'fecha_final' => $fechas->last() ?? $expediente->fecha_cierre?->format('Y-m-d'),
            'folios' => $expediente->soporte === 'electronico' ? $documentos->count() : (int) $expediente->folios_fisicos,
            // B19: con tomos, la caja y la carpeta de cada uno
            'caja' => $tomos->isNotEmpty() ? $tomos->pluck('caja')->filter()->unique()->implode(', ') : (string) $expediente->caja,
            'carpeta' => $tomos->isNotEmpty() ? $tomos->pluck('carpeta')->filter()->unique()->implode(', ') : (string) $expediente->carpeta,
            'tomos' => $tomos->isNotEmpty() ? $tomos->count() : '',
            'soporte' => ['electronico' => 'Electrónico', 'fisico' => 'Físico', 'hibrido' => 'Híbrido'][$expediente->soporte] ?? (string) $expediente->soporte,
            // Préstamos registrados del expediente: ninguno, 1-2 baja, 3-5 media, más alta
            'frecuencia' => match (true) {
                $prestamos === 0 => 'Ninguna',
                $prestamos <= 2 => 'Baja',
                $prestamos <= 5 => 'Media',
                default => 'Alta',
            },
            'notas' => trim("N.º {$expediente->numero_expediente}. Acceso: {$expediente->nivel_acceso}. ".mb_substr((string) $expediente->observaciones_generales, 0, 300)),
            'ubicacion_electronica' => $electronicos->isEmpty() ? '' : "SGDEA Ocobo, expediente {$expediente->numero_expediente}",
            'documentos_electronicos' => $electronicos->count(),
            'tamano' => (int) $electronicos->sum('tamano_bytes'),
            'indice_codigo' => (string) $version,
            'trd_version' => $expediente->trd_snapshot['version'] ?? null,
        ];
    }

    private function siguienteCodigo(string $tipo, Carbon $fecha): string
    {
        $prefijo = ($tipo === 'primaria' ? 'TP' : 'TS').'-'.$fecha->year.'-';
        $ultimo = OfiArchivoTransferenciaLote::where('codigo', 'like', $prefijo.'%')->lockForUpdate()->max('codigo');

        return $prefijo.str_pad((string) ((int) substr((string) $ultimo, strlen($prefijo)) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function notificar(OfiArchivoTransferenciaLote $lote): void
    {
        $recibido = $lote->estado === 'recibido';
        Notificacion::create([
            'user_id' => $lote->usuario_envia_id,
            'type' => $recibido ? 'transferencia_recibida' : 'transferencia_devuelta',
            'title' => $recibido ? 'Transferencia recibida' : 'Transferencia devuelta',
            'message' => $recibido
                ? "El lote {$lote->codigo} fue recibido en {$lote->destino}. El acta de entrega está disponible."
                : "El lote {$lote->codigo} fue devuelto con observaciones: ".mb_substr((string) $lote->observaciones_recepcion, 0, 200),
            'data' => ['lote_id' => $lote->id, 'codigo' => $lote->codigo, 'tipo_entidad' => 'transferencia_lote'],
        ]);
    }

    private static function firmar(OfiArchivoTransferenciaLote $lote, string $parte): string
    {
        $datos = $parte === 'envio'
            ? [$lote->codigo, $lote->tipo, $lote->hash_fuid, $lote->usuario_envia_id, $lote->fecha_envio->format('Y-m-d H:i:s'), $lote->codigo_verificacion]
            : [$lote->codigo, $lote->hash_fuid, $lote->hash_acta, $lote->usuario_recibe_id, $lote->fecha_recepcion->format('Y-m-d H:i:s'), $lote->codigo_verificacion];

        return hash_hmac('sha256', $parte.'|'.implode('|', $datos), hash_hmac('sha256', 'ocobo-transferencia-lote', (string) config('app.key')));
    }

    private static function nombre(User $user): string
    {
        return mb_substr(trim($user->nombres.' '.$user->apellidos) ?: 'Usuario #'.$user->id, 0, 255);
    }

    public static function entidad(): string
    {
        try {
            return ConfigVarias::getValor('razon_social_empresa') ?: config('app.entidad_nombre', 'OCOBO');
        } catch (\Throwable) {
            return config('app.entidad_nombre', 'OCOBO');
        }
    }
}
