<?php

namespace App\Services\OfiArchivo;

use App\Helpers\ArchivoHelper;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoDigitalizacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoSeleccionItem;
use App\Models\OfiArchivo\OfiArchivoSeleccionLote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use setasign\Fpdi\Fpdi;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * B22 (diseño de expedientes §3.8): disposición final desde el archivo central, con la
 * instantánea de la TRD tomada al cierre.
 *
 * - Al cumplirse la retención en central, el expediente pasa a 'en_disposicion'. CT queda para
 *   conservar (transferencia secundaria) y E para eliminar; S espera su lote de selección.
 * - Selección (S): el sistema propone la muestra con el método de la TRD (aleatorio, sistemático
 *   o cualitativo) y el archivo la ajusta. Al confirmar, lo seleccionado se conserva y el resto
 *   se elimina. Apartarse de la propuesta, o seleccionar a criterio, exige justificación.
 * - Digitalización (M/D): se registra la copia digital con su huella y un certificado. Es
 *   requisito antes de la disposición complementaria (CT o E) y nunca autoriza a eliminar el
 *   original por sí sola [Acuerdo 001/2024 A.4.3.2.5 par.].
 */
class DisposicionFinalService
{
    public const PERMISO_VER = 'Gestion de Archivo -> Disposicion -> Ver';

    public const PERMISO_SELECCIONAR = 'Gestion de Archivo -> Disposicion -> Seleccionar';

    public const PERMISO_DIGITALIZAR = 'Gestion de Archivo -> Disposicion -> Digitalizar';

    public const TIPO_NOTIFICACION = 'disposicion_archivo';

    public const EVENTO_INICIO = 'inicio_disposicion';

    public const EVENTO_SELECCION = 'seleccion_documental';

    public const EVENTO_DIGITALIZACION = 'digitalizacion';

    public const CONSERVAR = 'conservar';

    public const ELIMINAR = 'eliminar';

    public const DISCO = CierreExpedienteService::DISK;

    public const FORMATOS_DIGITALIZACION = [
        'application/pdf' => 'pdf',
        'image/tiff' => 'tif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public const MIN_JUSTIFICACION = 10;

    public function __construct(private ExpedienteEventosService $eventos) {}

    /** CT, E o S según la TRD que rige el expediente; null si no tiene. */
    public function disposicion(OfiArchivoExpediente $expediente, ?ClasificacionDocumentalTRD $trd = null): ?string
    {
        $trd ??= $expediente->trdRetencion();

        return match (true) {
            ! $trd => null,
            (bool) $trd->ct => 'CT',
            (bool) $trd->s => 'S',
            (bool) $trd->e => 'E',
            default => null,
        };
    }

    /** Conservar o eliminar; el de selección, el que fijó su lote (null mientras no se confirme). */
    public function destino(OfiArchivoExpediente $expediente, ?ClasificacionDocumentalTRD $trd = null): ?string
    {
        return match ($this->disposicion($expediente, $trd)) {
            'CT' => self::CONSERVAR,
            'E' => self::ELIMINAR,
            'S' => $expediente->destino_disposicion,
            default => null,
        };
    }

    public function requiereDigitalizacion(OfiArchivoExpediente $expediente, ?ClasificacionDocumentalTRD $trd = null): bool
    {
        return (bool) ($trd ?? $expediente->trdRetencion())?->m_d;
    }

    public function digitalizado(OfiArchivoExpediente $expediente): bool
    {
        return OfiArchivoDigitalizacion::where('expediente_id', $expediente->id)->exists();
    }

    /** Motivo por el que el expediente no puede seguir a $destino; null si puede. */
    public function motivoNoPuede(OfiArchivoExpediente $expediente, string $destino): ?string
    {
        $trd = $expediente->trdRetencion();
        $disposicion = $this->disposicion($expediente, $trd);
        if ($disposicion === 'S' && $expediente->destino_disposicion === null) {
            return 'Su disposición es selección y aún no se ha confirmado la selección de su serie';
        }
        $actual = $this->destino($expediente, $trd);
        if ($actual !== $destino) {
            return match ($destino) {
                self::CONSERVAR => $disposicion === 'S'
                    ? 'No quedó seleccionado para conservación'
                    : 'La disposición final de la TRD no es conservación total ni selección',
                default => $disposicion === 'S'
                    ? 'Quedó seleccionado para conservación'
                    : 'La disposición final de la TRD no permite eliminación (requiere E o S)',
            };
        }
        if ($this->requiereDigitalizacion($expediente, $trd) && ! $this->digitalizado($expediente)) {
            return 'Su disposición incluye digitalización (M/D) y aún no está registrada';
        }

        return null;
    }

    /**
     * Pasa a 'en_disposicion' los expedientes cerrados en el central cuya retención total ya se
     * cumplió, con su destino, y avisa al archivo.
     *
     * @return Collection<int, OfiArchivoExpediente>
     */
    public function marcarCumplidos(): Collection
    {
        $marcados = collect();

        OfiArchivoExpediente::where('fase', 'central')->where('estado', 'cerrado')->whereNotNull('fecha_cierre')
            ->orderBy('id')
            ->chunkById(200, function (Collection $expedientes) use ($marcados) {
                foreach ($expedientes as $expediente) {
                    $trd = $expediente->trdRetencion();
                    $disposicion = $this->disposicion($expediente, $trd);
                    $anios = (int) $trd?->a_g + (int) $trd?->a_c;
                    if (! $disposicion || TransferenciaLotesService::validarRetencion($expediente, $anios, 'total') !== null) {
                        continue;
                    }

                    $destino = $this->destino($expediente, $trd);
                    $actualizados = OfiArchivoExpediente::whereKey($expediente->id)->where('estado', 'cerrado')->update([
                        'estado' => 'en_disposicion',
                        'fecha_disposicion' => now(),
                        'destino_disposicion' => $destino,
                    ]);
                    if (! $actualizados) {
                        continue;
                    }
                    $this->eventos->registrar($expediente->id, self::EVENTO_INICIO, [
                        'disposicion' => $disposicion,
                        'destino' => $destino,
                        'digitalizacion' => $this->requiereDigitalizacion($expediente, $trd),
                    ]);
                    $marcados->push($expediente->setAttribute('destino_disposicion', $destino));
                }
            });

        $this->notificarInicio($marcados);

        return $marcados;
    }

    /**
     * Series y subseries de selección con expedientes esperando lote, con su método.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pendientesDeSeleccion(): Collection
    {
        return $this->poblacionSeleccion()
            ->groupBy(fn (OfiArchivoExpediente $e) => $e->trdRetencion()->id)
            ->map(function (Collection $expedientes, $trdId) {
                $trd = $expedientes->first()->trdRetencion();

                return [
                    'trd_id' => (int) $trdId,
                    'cod' => $trd->cod,
                    'nom' => $trd->nom,
                    'dependencia' => trim($expedientes->first()->dependencia_cod.' '.$expedientes->first()->dependencia_nom),
                    'metodo' => $trd->metodo_seleccion ?: 'cualitativo',
                    'porcentaje' => $trd->porcentaje_seleccion,
                    'intervalo' => $trd->intervalo_seleccion,
                    'expedientes' => $expedientes->count(),
                ];
            })->values();
    }

    /**
     * Propone el lote de selección de una serie o subserie con todos sus expedientes pendientes.
     */
    public function proponer(int $trdId, User $usuario): OfiArchivoSeleccionLote
    {
        return DB::transaction(function () use ($trdId, $usuario) {
            $poblacion = $this->poblacionSeleccion($trdId, true)
                ->sortBy(fn (OfiArchivoExpediente $e) => [$e->fecha_cierre?->timestamp, $e->id])->values();
            if ($poblacion->isEmpty()) {
                throw ValidationException::withMessages(['trd_id' => 'La serie no tiene expedientes de selección pendientes']);
            }

            $trd = $poblacion->first()->trdRetencion();
            $metodo = $trd->metodo_seleccion ?: 'cualitativo';
            $total = $poblacion->count();
            $semilla = $metodo === 'aleatorio' ? random_int(1, 2_147_483_647) : null;
            $propuestos = match ($metodo) {
                // La semilla queda en el lote: con ella se reproduce la misma muestra
                'aleatorio' => array_slice((new Randomizer(new Mt19937($semilla)))->shuffleArray(range(1, $total)), 0,
                    max(1, (int) ceil($total * (int) $trd->porcentaje_seleccion / 100))),
                'sistematico' => array_values(array_filter(range(1, $total), fn ($n) => ($n - 1) % max(2, (int) $trd->intervalo_seleccion) === 0)),
                default => [],
            };

            $lote = OfiArchivoSeleccionLote::create([
                'codigo' => $this->siguienteCodigo(now()),
                'trd_id' => $trdId,
                'trd_cod' => (string) $trd->cod,
                'trd_nom' => (string) $trd->nom,
                'metodo' => $metodo,
                'porcentaje' => $metodo === 'aleatorio' ? $trd->porcentaje_seleccion : null,
                'intervalo' => $metodo === 'sistematico' ? $trd->intervalo_seleccion : null,
                'semilla' => $semilla,
                'estado' => OfiArchivoSeleccionLote::PROPUESTA,
                'user_id' => $usuario->id,
            ]);
            foreach ($poblacion as $i => $expediente) {
                $propuesto = in_array($i + 1, $propuestos, true);
                OfiArchivoSeleccionItem::create([
                    'lote_id' => $lote->id,
                    'expediente_id' => $expediente->id,
                    'orden' => $i + 1,
                    'propuesto' => $propuesto,
                    'seleccionado' => $propuesto,
                ]);
            }

            return $lote;
        });
    }

    public function ajustar(OfiArchivoSeleccionLote $lote, int $itemId, bool $seleccionado): OfiArchivoSeleccionItem
    {
        $this->asegurarPropuesta($lote);
        $item = $lote->items()->whereKey($itemId)->firstOrFail();
        $item->update(['seleccionado' => $seleccionado]);

        return $item;
    }

    /**
     * Confirma la selección: lo seleccionado se conserva y el resto pasa a eliminación.
     */
    public function confirmar(OfiArchivoSeleccionLote $lote, ?string $justificacion, User $usuario): OfiArchivoSeleccionLote
    {
        return DB::transaction(function () use ($lote, $justificacion, $usuario) {
            $lote = OfiArchivoSeleccionLote::lockForUpdate()->findOrFail($lote->id);
            $this->asegurarPropuesta($lote);

            $items = $lote->items()->with('expediente')->get();
            $cambios = $items->filter(fn (OfiArchivoSeleccionItem $i) => $i->propuesto !== $i->seleccionado)->count();
            $justificacion = trim((string) $justificacion);
            if (($lote->metodo === 'cualitativo' || $cambios > 0) && mb_strlen($justificacion) < self::MIN_JUSTIFICACION) {
                throw ValidationException::withMessages(['justificacion' => $lote->metodo === 'cualitativo'
                    ? 'Explique el criterio con que seleccionó los expedientes (al menos '.self::MIN_JUSTIFICACION.' caracteres)'
                    : "Explique por qué cambió {$cambios} expediente(s) de la propuesta (al menos ".self::MIN_JUSTIFICACION.' caracteres)']);
            }

            foreach ($items as $item) {
                $expediente = $item->expediente;
                if ($expediente->estado !== 'en_disposicion' || $expediente->destino_disposicion !== null) {
                    throw ValidationException::withMessages(['lote' => "El expediente {$expediente->numero_expediente} ya no está pendiente de selección"]);
                }
                $destino = $item->seleccionado ? self::CONSERVAR : self::ELIMINAR;
                OfiArchivoExpediente::whereKey($expediente->id)->update(['destino_disposicion' => $destino]);
                $this->eventos->registrar($expediente->id, self::EVENTO_SELECCION, [
                    'lote' => $lote->codigo,
                    'metodo' => $lote->metodo,
                    'propuesto' => $item->propuesto,
                    'seleccionado' => $item->seleccionado,
                    'destino' => $destino,
                ]);
            }

            $lote->update([
                'estado' => OfiArchivoSeleccionLote::CONFIRMADA,
                'justificacion' => $justificacion ?: null,
                'confirmado_por_id' => $usuario->id,
                'confirmado_at' => now(),
            ]);

            return $lote;
        });
    }

    /** Descarta una propuesta para volver a proponer; sus expedientes siguen pendientes. */
    public function anular(OfiArchivoSeleccionLote $lote): OfiArchivoSeleccionLote
    {
        $this->asegurarPropuesta($lote);
        $lote->update(['estado' => OfiArchivoSeleccionLote::ANULADA]);

        return $lote;
    }

    /**
     * Registra la digitalización (M/D) del expediente con la huella de la copia y su certificado.
     *
     * @param  array{paginas?: ?int, resolucion_dpi?: ?int, observaciones?: ?string}  $datos
     */
    public function digitalizar(OfiArchivoExpediente $expediente, UploadedFile $archivo, array $datos, User $usuario): OfiArchivoDigitalizacion
    {
        if ($expediente->estado !== 'en_disposicion') {
            throw ValidationException::withMessages(['expediente' => 'Solo se digitaliza un expediente en disposición final']);
        }
        if (! $this->requiereDigitalizacion($expediente)) {
            throw ValidationException::withMessages(['expediente' => 'La disposición de la TRD del expediente no incluye digitalización (M/D)']);
        }
        $validacion = ArchivoHelper::validarArchivoSeguro($archivo, array_keys(self::FORMATOS_DIGITALIZACION));
        if (! $validacion['valido']) {
            throw ValidationException::withMessages(['archivo' => $validacion['error'] ?? 'Archivo no válido']);
        }

        $formato = $validacion['mime_real'];
        $path = $archivo->storeAs('digitalizaciones/'.$expediente->id, Str::random(40).'.'.self::FORMATOS_DIGITALIZACION[$formato], self::DISCO);
        if (! $path) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo guardar la copia digital']);
        }

        try {
            return DB::transaction(function () use ($expediente, $archivo, $datos, $usuario, $formato, $path) {
                $digitalizacion = new OfiArchivoDigitalizacion([
                    'expediente_id' => $expediente->id,
                    'archivo_path' => $path,
                    'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                    'formato' => $formato,
                    'tamano_bytes' => (int) $archivo->getSize(),
                    'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                    'paginas' => $datos['paginas'] ?? null,
                    'resolucion_dpi' => $datos['resolucion_dpi'] ?? null,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'codigo_verificacion' => 'DG-'.implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4)),
                    'user_id' => $usuario->id,
                    'digitalizado_at' => now()->startOfSecond(),
                ]);
                $digitalizacion->certificado = $this->certificado($digitalizacion, $expediente, $usuario);
                $digitalizacion->hash_certificado = hash('sha256', $digitalizacion->certificado);
                $digitalizacion->firma = self::firmar($digitalizacion);
                $digitalizacion->save();

                $this->eventos->registrar($expediente->id, self::EVENTO_DIGITALIZACION, [
                    'digitalizacion_id' => $digitalizacion->id,
                    'nombre_original' => $digitalizacion->nombre_original,
                    'hash_sha256' => $digitalizacion->hash_sha256,
                    'codigo_verificacion' => $digitalizacion->codigo_verificacion,
                ]);

                return $digitalizacion;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISCO)->delete($path);

            throw $e;
        }
    }

    /**
     * Comprueba que la copia digital y el certificado son los que se registraron.
     *
     * @return array{integro: bool, problemas: list<string>}
     */
    public function verificar(OfiArchivoDigitalizacion $digitalizacion): array
    {
        $problemas = [];
        $calculado = ArchivoFolio::hashCalculado(['disk' => self::DISCO, 'path' => $digitalizacion->archivo_path]);
        if ($calculado === null) {
            $problemas[] = 'No se encuentra la copia digital';
        } elseif (! hash_equals($digitalizacion->hash_sha256, $calculado)) {
            $problemas[] = 'La copia digital no coincide con su huella';
        }
        if (! hash_equals($digitalizacion->hash_certificado, hash('sha256', (string) $digitalizacion->certificado))) {
            $problemas[] = 'El certificado no coincide con su huella';
        }
        if (! hash_equals($digitalizacion->firma, self::firmar($digitalizacion))) {
            $problemas[] = 'La firma no corresponde al registro';
        }

        return ['integro' => $problemas === [], 'problemas' => $problemas];
    }

    /**
     * Expedientes en disposición, de selección y sin destino, que no están en una propuesta.
     *
     * @return Collection<int, OfiArchivoExpediente>
     */
    private function poblacionSeleccion(?int $trdId = null, bool $bloquear = false): Collection
    {
        $query = OfiArchivoExpediente::where('estado', 'en_disposicion')->whereNull('destino_disposicion')
            ->whereNotIn('id', OfiArchivoSeleccionItem::select('expediente_id')
                ->whereIn('lote_id', OfiArchivoSeleccionLote::select('id')->where('estado', OfiArchivoSeleccionLote::PROPUESTA)));
        if ($bloquear) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(function (OfiArchivoExpediente $expediente) use ($trdId) {
            $trd = $expediente->trdRetencion();

            return $this->disposicion($expediente, $trd) === 'S' && ($trdId === null || (int) $trd->id === $trdId);
        })->values();
    }

    private function asegurarPropuesta(OfiArchivoSeleccionLote $lote): void
    {
        if ($lote->estado !== OfiArchivoSeleccionLote::PROPUESTA) {
            throw ValidationException::withMessages(['lote' => "La selección {$lote->codigo} ya fue {$lote->estado}"]);
        }
    }

    private function siguienteCodigo(Carbon $fecha): string
    {
        $prefijo = 'SEL-'.$fecha->year.'-';
        $ultimo = OfiArchivoSeleccionLote::where('codigo', 'like', $prefijo.'%')->lockForUpdate()->max('codigo');

        return $prefijo.str_pad((string) ((int) substr((string) $ultimo, strlen($prefijo)) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function certificado(OfiArchivoDigitalizacion $d, OfiArchivoExpediente $expediente, User $usuario): string
    {
        $txt = fn (?string $s) => mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');
        $trd = $expediente->trdRetencion();
        $complementaria = $this->destino($expediente, $trd);

        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->MultiCell(0, 7, $txt('CERTIFICADO DE DIGITALIZACIÓN'), 0, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $txt(TransferenciaLotesService::entidad()), 0, 1, 'C');
        $pdf->Ln(4);

        $datos = [
            'Expediente' => $expediente->numero_expediente,
            'Nombre' => $expediente->nombre_expediente,
            'Oficina productora' => trim($expediente->dependencia_cod.' '.$expediente->dependencia_nom),
            'Serie / subserie' => trim(($trd?->cod ?? '').' '.($trd?->nom ?? '')),
            'Disposición final (TRD)' => $trd?->disposicion_final,
            'Folios físicos' => $expediente->folios_fisicos !== null ? (string) $expediente->folios_fisicos : 'No registrados',
            'Archivo digital' => $d->nombre_original,
            'Formato' => $d->formato,
            'Tamaño' => number_format($d->tamano_bytes, 0, ',', '.').' bytes',
            'Páginas' => $d->paginas !== null ? (string) $d->paginas : 'No indicadas',
            'Resolución' => $d->resolucion_dpi !== null ? $d->resolucion_dpi.' dpi' : 'No indicada',
            'Huella SHA-256' => $d->hash_sha256,
            'Fecha' => $d->digitalizado_at->format('Y-m-d H:i:s'),
            'Responsable' => mb_substr(trim($usuario->nombres.' '.$usuario->apellidos) ?: 'Usuario #'.$usuario->id, 0, 255),
            'Código de verificación' => $d->codigo_verificacion,
        ];
        foreach ($datos as $campo => $valor) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(50, 6, $txt($campo), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 6, $txt((string) $valor), 0, 'L');
        }
        if ($d->observaciones) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 6, $txt('Observaciones'), 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 5, $txt($d->observaciones), 0, 'L');
        }

        $pdf->Ln(4);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, $txt('Se certifica que la copia digital identificada con la huella anterior corresponde al expediente '
            .'descrito. La digitalización no reemplaza al original ni autoriza a eliminarlo (Acuerdo AGN 001 de 2024, A.4.3.2.5 parágrafo); '
            .'el original sigue su disposición complementaria: '.match ($complementaria) {
                self::CONSERVAR => 'conservación',
                self::ELIMINAR => 'eliminación, por su procedimiento',
                default => 'pendiente de la selección',
            }.'.'), 0, 'J');

        return $pdf->Output('S');
    }

    private static function firmar(OfiArchivoDigitalizacion $d): string
    {
        $datos = [$d->codigo_verificacion, $d->expediente_id, $d->hash_sha256, $d->hash_certificado, $d->user_id, $d->digitalizado_at->format('Y-m-d H:i:s')];

        return hash_hmac('sha256', implode('|', $datos), hash_hmac('sha256', 'ocobo-digitalizacion', (string) config('app.key')));
    }

    private function notificarInicio(Collection $marcados): void
    {
        if ($marcados->isEmpty()) {
            return;
        }

        try {
            $usuarios = User::permission(self::PERMISO_VER)->pluck('id')->all();
        } catch (PermissionDoesNotExist) {
            return;
        }

        $conteo = $marcados->countBy(fn (OfiArchivoExpediente $e) => $e->destino_disposicion ?? 'seleccion');
        $partes = array_filter([
            isset($conteo[self::CONSERVAR]) ? "{$conteo[self::CONSERVAR]} para conservar" : null,
            isset($conteo[self::ELIMINAR]) ? "{$conteo[self::ELIMINAR]} para eliminar" : null,
            isset($conteo['seleccion']) ? "{$conteo['seleccion']} pendientes de selección" : null,
        ]);
        foreach ($usuarios as $userId) {
            Notificacion::create([
                'user_id' => $userId,
                'type' => self::TIPO_NOTIFICACION,
                'title' => 'Expedientes en disposición final',
                'message' => "Se cumplió la retención de {$marcados->count()} expediente(s) en el archivo central: ".implode(', ', $partes)
                    .'. Revíselos en Gestión de Archivo → Disposición final.',
                'data' => ['tipo_entidad' => 'disposicion', 'expediente_ids' => $marcados->pluck('id')->values()->all()],
            ]);
        }
    }
}
