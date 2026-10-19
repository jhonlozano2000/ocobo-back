<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteHojaControl;
use App\Models\OfiArchivo\OfiArchivoExpedienteHojaControlVersion;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\OfiArchivo\OfiArchivoExpedienteTomo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;

/**
 * B19 (diseño de expedientes §3.3): hoja de control y tomos de los expedientes físicos e híbridos
 * [Acuerdo AGN 001/2024 A.4.3.1.8 num. 5].
 *
 * Cada documento físico se registra con su tipo documental y su número de folios; el sistema le
 * asigna el rango de folios siguiente y el tomo. Cuando el documento no cabe en el tomo actual
 * (D3: 200 folios, configurable) se abre el siguiente, con la foliación continua. Al cerrar el
 * expediente la hoja se congela en PDF y se firma, como el índice electrónico.
 */
class HojaControlService
{
    private static ?bool $disponible = null;

    public function __construct(
        private ExpedienteEventosService $eventos,
        private TiposDocumentalesExpediente $tipos,
    ) {}

    /** Las tablas llegan con la migración 2026_10_19_200000. */
    public static function disponible(): bool
    {
        return self::$disponible ??= Schema::hasTable('ofi_archivo_expediente_hoja_control');
    }

    public static function aplica(OfiArchivoExpediente $expediente): bool
    {
        return in_array($expediente->soporte, ['fisico', 'hibrido'], true);
    }

    public static function foliosPorTomo(): int
    {
        return max(1, (int) config('archivo.folios_por_tomo', 200));
    }

    /**
     * Registra un documento físico; el expediente debe llegar bloqueado y abierto.
     *
     * @param  array{tipo_documental_id: int, fecha_documento?: ?string, folios: int, observaciones?: ?string}  $datos
     */
    public function registrar(OfiArchivoExpediente $expediente, array $datos, User $usuario): OfiArchivoExpedienteHojaControl
    {
        $this->exigirEditable($expediente);

        $tipo = $this->tipos->resolver($expediente, (int) $datos['tipo_documental_id']);
        if (is_string($tipo)) {
            throw ValidationException::withMessages(['tipo_documental_id' => [$tipo]]);
        }

        $folios = (int) $datos['folios'];
        $inicial = $this->siguienteFolio($expediente);
        $final = $inicial + $folios - 1;

        $tomo = $this->tomoActual($expediente) ?? $this->crearTomo($expediente, 1, 1, $usuario);
        // El documento no se parte entre tomos: si no cabe, va completo al siguiente
        if ($this->tieneRegistros($tomo) && $final - $tomo->folio_inicial + 1 > self::foliosPorTomo()) {
            $tomo = $this->crearTomo($expediente, $tomo->numero + 1, $inicial, $usuario, $tomo);
        }

        $registro = OfiArchivoExpedienteHojaControl::create([
            'expediente_id' => $expediente->id,
            'tomo_id' => $tomo->id,
            'orden' => (int) OfiArchivoExpedienteHojaControl::where('expediente_id', $expediente->id)->max('orden') + 1,
            'tipo_documental_id' => $tipo->id,
            'tipo_documental' => $tipo->nom,
            'fecha_documento' => $datos['fecha_documento'] ?? null,
            'folio_inicial' => $inicial,
            'folio_final' => $final,
            'observaciones' => $datos['observaciones'] ?? null,
            'usuario_id' => $usuario->id,
        ]);

        $this->sincronizarFolios($expediente);
        $this->eventos->registrar($expediente->id, 'hoja_control_registro', [
            'orden' => $registro->orden,
            'tipo_documental' => $registro->tipo_documental,
            'folios' => "{$inicial}-{$final}",
            'tomo' => $tomo->numero,
        ]);

        return $registro;
    }

    /** Anula un registro: el documento sale del expediente y sus folios no se renumeran. */
    public function anular(OfiArchivoExpediente $expediente, OfiArchivoExpedienteHojaControl $registro, string $motivo, User $usuario): void
    {
        $this->exigirEditable($expediente);
        if ($registro->anulado_en) {
            throw ValidationException::withMessages(['registro' => ['El registro ya está anulado.']]);
        }

        $registro->update(['anulado_en' => now(), 'motivo_anulacion' => $motivo, 'anulado_por' => $usuario->id]);

        $this->sincronizarFolios($expediente);
        $this->eventos->registrar($expediente->id, 'hoja_control_anulacion', [
            'orden' => $registro->orden,
            'tipo_documental' => $registro->tipo_documental,
            'folios' => "{$registro->folio_inicial}-{$registro->folio_final}",
            'motivo' => $motivo,
        ]);
    }

    /** Abre el tomo siguiente antes de llenar el actual (la carpeta física se cerró antes). */
    public function abrirTomo(OfiArchivoExpediente $expediente, array $ubicacion, User $usuario): OfiArchivoExpedienteTomo
    {
        $this->exigirEditable($expediente);

        $actual = $this->tomoActual($expediente);
        if (! $actual || ! $this->tieneRegistros($actual)) {
            throw ValidationException::withMessages(['tomo' => ['El tomo actual no tiene documentos; regístrelos antes de abrir otro.']]);
        }

        return $this->crearTomo($expediente, $actual->numero + 1, $this->siguienteFolio($expediente), $usuario, $actual, $ubicacion);
    }

    /** La ubicación del tomo cambia también después del cierre (traslados y transferencias). */
    public function ubicarTomo(OfiArchivoExpedienteTomo $tomo, array $ubicacion): OfiArchivoExpedienteTomo
    {
        $tomo->fill(array_intersect_key($ubicacion, array_flip(['ubicacion_fisica', 'caja', 'carpeta'])));
        $cambios = collect($tomo->getDirty())->map(fn ($despues, $campo) => ['antes' => $tomo->getOriginal($campo), 'despues' => $despues]);

        if ($cambios->isNotEmpty()) {
            $tomo->save();
            $this->eventos->registrar($tomo->expediente_id, 'tomo_ubicacion', ['tomo' => $tomo->numero, 'cambios' => $cambios->all()]);
        }

        return $tomo;
    }

    /**
     * Tomos con su rango de folios y fechas extremas, calculados de sus registros activos.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tomos(OfiArchivoExpediente $expediente): Collection
    {
        $registros = OfiArchivoExpedienteHojaControl::where('expediente_id', $expediente->id)->get()->groupBy('tomo_id');

        return OfiArchivoExpedienteTomo::where('expediente_id', $expediente->id)->orderBy('numero')->get()
            ->map(function (OfiArchivoExpedienteTomo $tomo) use ($registros) {
                $todos = $registros->get($tomo->id, collect());
                $activos = $todos->whereNull('anulado_en');
                $fechas = $activos->pluck('fecha_documento')->filter()->map->toDateString()->sort()->values();

                return [
                    'id' => $tomo->id,
                    'numero' => $tomo->numero,
                    'folio_inicial' => $tomo->folio_inicial,
                    'folio_final' => $todos->max('folio_final'),
                    'folios' => $activos->sum(fn ($r) => $r->folios()),
                    'documentos' => $activos->count(),
                    'fecha_inicial' => $fechas->first(),
                    'fecha_final' => $fechas->last(),
                    'ubicacion_fisica' => $tomo->ubicacion_fisica,
                    'caja' => $tomo->caja,
                    'carpeta' => $tomo->carpeta,
                ];
            })->values();
    }

    /**
     * Registros de la hoja en su orden, con el número de tomo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(OfiArchivoExpediente $expediente): Collection
    {
        return OfiArchivoExpedienteHojaControl::with('tomo:id,numero')->where('expediente_id', $expediente->id)->orderBy('orden')->get()
            ->map(fn (OfiArchivoExpedienteHojaControl $r) => [
                'id' => $r->id,
                'orden' => $r->orden,
                'tomo' => $r->tomo?->numero,
                'tipo_documental_id' => $r->tipo_documental_id,
                'tipo_documental' => $r->tipo_documental,
                'fecha_documento' => $r->fecha_documento?->toDateString(),
                'folio_inicial' => $r->folio_inicial,
                'folio_final' => $r->folio_final,
                'folios' => $r->folios(),
                'observaciones' => $r->observaciones,
                'anulado' => $r->anulado_en !== null,
                'motivo_anulacion' => $r->motivo_anulacion,
            ])->values();
    }

    /**
     * Huella de lo que se firma: los registros y la foliación de cada tomo. La ubicación del tomo
     * queda fuera porque cambia después del cierre (traslados, transferencias).
     */
    public function hash(OfiArchivoExpediente $expediente): string
    {
        $contenido = [
            'registros' => $this->filas($expediente)->map(fn ($f) => array_diff_key($f, ['id' => true]))->all(),
            'tomos' => $this->tomos($expediente)
                ->map(fn ($t) => array_diff_key($t, array_flip(['id', 'ubicacion_fisica', 'caja', 'carpeta'])))->all(),
        ];

        return hash('sha256', json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function ultimaVersion(OfiArchivoExpediente $expediente): ?OfiArchivoExpedienteHojaControlVersion
    {
        return OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $expediente->id)->latest('version')->first();
    }

    /**
     * Congela y firma la hoja al cerrar (firma electrónica interna, D2). En el híbrido queda la
     * referencia cruzada con el índice electrónico firmado en el mismo cierre.
     */
    public function versionar(OfiArchivoExpediente $expediente, User $firmante, ?OfiArchivoExpedienteIndiceVersion $indice = null, string $motivo = 'cierre'): OfiArchivoExpedienteHojaControlVersion
    {
        $firma = [
            'version' => (int) OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $expediente->id)->max('version') + 1,
            'firmante' => trim($firmante->nombres.' '.$firmante->apellidos) ?: 'Usuario #'.$firmante->id,
            'firmado_en' => now()->startOfSecond(),
            'codigo' => 'HC-'.implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4)),
        ];
        $pdf = $this->pdf($expediente, $firma, $indice);
        $tomos = $this->tomos($expediente);

        $version = new OfiArchivoExpedienteHojaControlVersion([
            'expediente_id' => $expediente->id,
            'version' => $firma['version'],
            'motivo' => $motivo,
            'total_documentos' => $tomos->sum('documentos'),
            'total_folios' => $tomos->sum('folios'),
            'total_tomos' => $tomos->count(),
            'hash_contenido' => $this->hash($expediente),
            'pdf' => $pdf,
            'hash_pdf' => hash('sha256', $pdf),
            'indice_version_id' => $indice?->id,
            'firma_tipo' => 'electronica_interna',
            'firmado_por' => $firmante->id,
            'firmante' => mb_substr($firma['firmante'], 0, 255),
            'firmado_en' => $firma['firmado_en'],
            'codigo_verificacion' => $firma['codigo'],
        ]);
        $version->firma = self::firmar($version);
        $version->save();

        $this->eventos->registrar($expediente->id, 'hoja_control_firmada', [
            'version' => $version->version,
            'codigo_verificacion' => $version->codigo_verificacion,
            'hash_pdf' => $version->hash_pdf,
            'folios' => $version->total_folios,
            'tomos' => $version->total_tomos,
        ]);

        return $version;
    }

    /**
     * La versión es íntegra si el PDF y la firma coinciden y la hoja no cambió después de firmarla.
     *
     * @return array{valida: bool, motivo: ?string}
     */
    public function verificarVersion(OfiArchivoExpedienteHojaControlVersion $version, OfiArchivoExpediente $expediente): array
    {
        $motivo = match (true) {
            ! hash_equals($version->hash_pdf, hash('sha256', (string) $version->pdf)) => 'El PDF no coincide con su huella',
            ! hash_equals($version->firma, self::firmar($version)) => 'La firma no corresponde a la versión',
            ! hash_equals($version->hash_contenido, $this->hash($expediente)) => 'La hoja cambió después de firmarse',
            default => null,
        };

        return ['valida' => $motivo === null, 'motivo' => $motivo];
    }

    /**
     * Hoja de control en PDF; sin firma es un borrador.
     *
     * @param  array{version: int, firmante: string, firmado_en: \DateTimeInterface, codigo: string}|null  $firma
     */
    public function pdf(OfiArchivoExpediente $expediente, ?array $firma = null, ?OfiArchivoExpedienteIndiceVersion $indice = null): string
    {
        $expediente->loadMissing(['serieTrd:id,cod,nom', 'subserieTrd:id,cod,nom', 'dependencia']);
        $filas = $this->filas($expediente);
        $tomos = $this->tomos($expediente);
        $txt = fn (?string $s) => mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');

        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, $txt('HOJA DE CONTROL DEL EXPEDIENTE'), 0, 1, 'C');
        if (! $firma) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(180, 0, 0);
            $pdf->Cell(0, 6, $txt('BORRADOR - SIN FIRMAR: la hoja se firma al cerrar el expediente'), 0, 1, 'C');
            $pdf->SetTextColor(0);
        }
        $pdf->Ln(2);

        $pdf->SetFont('Arial', '', 9);
        $serie = $expediente->serieTrd;
        $subserie = $expediente->subserieTrd;
        $datos = [
            'Entidad' => TransferenciaLotesService::entidad(),
            'Unidad administrativa' => $expediente->dependencia_nom ?? $expediente->dependencia?->nom_organico,
            'Serie / Subserie' => trim(($serie ? "{$serie->cod} {$serie->nom}" : '').($subserie ? " / {$subserie->cod} {$subserie->nom}" : '')),
            'No. expediente' => $expediente->numero_expediente.' - '.$expediente->nombre_expediente,
            'Fecha de apertura' => $expediente->fecha_apertura?->format('Y-m-d')
                .($expediente->fecha_cierre ? '   Fecha de cierre: '.$expediente->fecha_cierre->format('Y-m-d') : ''),
            'Soporte' => $expediente->soporte === 'hibrido' ? 'Híbrido (físico y electrónico)' : 'Físico',
        ];
        foreach ($datos as $campo => $valor) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 6, $txt($campo.':'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, $txt((string) $valor), 0, 1);
        }
        $pdf->Ln(3);

        $cols = [[14, 'Orden'], [14, 'Tomo'], [24, 'Fecha doc.'], [90, 'Tipo documental'], [20, 'Folio desde'], [20, 'Folio hasta'], [14, 'Folios'], [81, 'Observaciones']];
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        foreach ($cols as [$ancho, $titulo]) {
            $pdf->Cell($ancho, 7, $txt($titulo), 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        foreach ($filas as $f) {
            $pdf->SetTextColor($f['anulado'] ? 150 : 0);
            $obs = $f['anulado'] ? 'ANULADO: '.$f['motivo_anulacion'] : (string) $f['observaciones'];
            $pdf->Cell(14, 6, (string) $f['orden'], 1, 0, 'C');
            $pdf->Cell(14, 6, (string) $f['tomo'], 1, 0, 'C');
            $pdf->Cell(24, 6, (string) $f['fecha_documento'], 1, 0, 'C');
            $pdf->Cell(90, 6, $txt(mb_substr($f['tipo_documental'], 0, 60)), 1, 0);
            $pdf->Cell(20, 6, (string) $f['folio_inicial'], 1, 0, 'C');
            $pdf->Cell(20, 6, (string) $f['folio_final'], 1, 0, 'C');
            $pdf->Cell(14, 6, (string) $f['folios'], 1, 0, 'C');
            $pdf->Cell(81, 6, $txt(mb_substr($obs, 0, 55)), 1, 1);
        }
        $pdf->SetTextColor(0);
        if ($filas->isEmpty()) {
            $pdf->Cell(277, 6, $txt('Sin documentos físicos registrados'), 1, 1, 'C');
        }

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, $txt('Tomos: '.$tomos->count().' | Documentos: '.$tomos->sum('documentos').' | Folios: '.$tomos->sum('folios')
            .' | Máximo por tomo: '.self::foliosPorTomo()), 0, 1);
        $pdf->SetFont('Arial', '', 8);
        foreach ($tomos as $t) {
            $pdf->Cell(0, 5, $txt("Tomo {$t['numero']}: folios {$t['folio_inicial']}-".($t['folio_final'] ?? $t['folio_inicial'])
                .' | fechas '.($t['fecha_inicial'] ?? '—').' a '.($t['fecha_final'] ?? '—')
                .' | '.collect(['Ubicación' => $t['ubicacion_fisica'], 'Caja' => $t['caja'], 'Carpeta' => $t['carpeta']])
                    ->map(fn ($v, $k) => $k.': '.($v ?: 'sin registrar'))->implode(' | ')), 0, 1);
        }

        if ($expediente->soporte === 'hibrido') {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->MultiCell(0, 5, $txt('Expediente híbrido: los documentos electrónicos se relacionan en el índice electrónico'
                .($indice ? " (versión {$indice->version}, código de verificación {$indice->codigo_verificacion})" : '').'.'));
        }

        $pdf->Ln(3);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, $txt('Huella de la hoja (SHA-256): '.$this->hash($expediente)), 0, 1);
        if ($firma) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $txt("Firmado electrónicamente por {$firma['firmante']} el ".$firma['firmado_en']->format('Y-m-d H:i:s')
                ." (versión {$firma['version']} de la hoja de control)."), 0, 1);
            $pdf->Cell(0, 5, $txt("Código de verificación: {$firma['codigo']}"), 0, 1);
        }

        return $pdf->Output('S');
    }

    private static function firmar(OfiArchivoExpedienteHojaControlVersion $version): string
    {
        $datos = implode('|', [
            $version->expediente_id,
            $version->version,
            $version->motivo,
            $version->total_documentos,
            $version->total_folios,
            $version->total_tomos,
            $version->hash_contenido,
            $version->hash_pdf,
            $version->indice_version_id,
            $version->firmado_por,
            $version->firmante,
            $version->firmado_en->format('Y-m-d H:i:s'),
            $version->codigo_verificacion,
        ]);

        return hash_hmac('sha256', $datos, hash_hmac('sha256', 'ocobo-hoja-control-expediente', (string) config('app.key')));
    }

    private function exigirEditable(OfiArchivoExpediente $expediente): void
    {
        if (! self::aplica($expediente)) {
            throw ValidationException::withMessages(['expediente' => ['La hoja de control es de los expedientes físicos e híbridos.']]);
        }
        if ($expediente->estado !== 'abierto') {
            throw ValidationException::withMessages(['expediente' => ['Solo se modifica la hoja de control de un expediente abierto.']]);
        }
    }

    private function siguienteFolio(OfiArchivoExpediente $expediente): int
    {
        return (int) OfiArchivoExpedienteHojaControl::where('expediente_id', $expediente->id)->max('folio_final') + 1;
    }

    private function tomoActual(OfiArchivoExpediente $expediente): ?OfiArchivoExpedienteTomo
    {
        return OfiArchivoExpedienteTomo::where('expediente_id', $expediente->id)->orderByDesc('numero')->first();
    }

    private function tieneRegistros(OfiArchivoExpedienteTomo $tomo): bool
    {
        return OfiArchivoExpedienteHojaControl::where('tomo_id', $tomo->id)->exists();
    }

    /** El tomo nuevo hereda la ubicación del anterior (o la del expediente) si no se indica otra. */
    private function crearTomo(OfiArchivoExpediente $expediente, int $numero, int $folioInicial, User $usuario, ?OfiArchivoExpedienteTomo $anterior = null, array $ubicacion = []): OfiArchivoExpedienteTomo
    {
        $origen = $anterior ?? $expediente;
        $tomo = OfiArchivoExpedienteTomo::create([
            'expediente_id' => $expediente->id,
            'numero' => $numero,
            'folio_inicial' => $folioInicial,
            'usuario_id' => $usuario->id,
        ] + collect(['ubicacion_fisica', 'caja', 'carpeta'])
            ->mapWithKeys(fn ($campo) => [$campo => filled($ubicacion[$campo] ?? null) ? $ubicacion[$campo] : $origen->{$campo}])->all());

        $this->eventos->registrar($expediente->id, 'tomo_abierto', ['tomo' => $numero, 'folio_inicial' => $folioInicial]);

        return $tomo;
    }

    /** Con hoja de control, los folios físicos del expediente son los de sus registros activos. */
    private function sincronizarFolios(OfiArchivoExpediente $expediente): void
    {
        $folios = OfiArchivoExpedienteHojaControl::activos()->where('expediente_id', $expediente->id)
            ->get(['folio_inicial', 'folio_final'])->sum(fn ($r) => $r->folios());

        // El historial ya registra el documento; la edición derivada no se registra aparte
        $expediente->updateQuietly(['folios_fisicos' => $folios]);
    }
}
