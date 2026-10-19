<?php

namespace App\Services\OfiArchivo;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoDepuracionDigital;
use App\Models\OfiArchivo\OfiArchivoEliminacionItem;
use App\Models\OfiArchivo\OfiArchivoEliminacionLote;
use App\Models\OfiArchivo\OfiArchivoEliminacionObservacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use App\Services\Configuracion\BusinessDaysService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * B18 (diseño de expedientes §3.8 E): eliminación documental por lote [Acuerdo 001/2024].
 *
 * 1. Solo con la TRD inscrita en el RUSD. Un lote por serie o subserie completa: todos sus
 *    expedientes en disposición con destino eliminar.
 * 2. El inventario se publica 60 días hábiles; la ciudadanía deja observaciones. Al cerrar la
 *    publicación, si hubo, el comité emite concepto en 30 días hábiles: continuar, suspender,
 *    ampliar la retención o cambiar la disposición (estas tres sacan los expedientes del lote).
 * 3. El Comité Institucional aprueba (número y fecha de su acta, método); el presidente y el
 *    secretario técnico firman.
 * 4. Lo ejecuta alguien distinto de quien lo propuso: el expediente queda 'eliminado' con su
 *    metadata, su índice y su historial; se borran los binarios propios (sobrescritos antes en
 *    discos locales) y los radicados que siguen en otro expediente solo se desvinculan. Cada
 *    borrado queda en la bitácora de depuraciones y el acta, firmada y publicada.
 */
class EliminacionDocumentalService
{
    public const PERMISO_VER = 'Gestion de Archivo -> Eliminaciones -> Ver';

    public const PERMISO_PROPONER = 'Gestion de Archivo -> Eliminaciones -> Proponer';

    public const PERMISO_COMITE = 'Gestion de Archivo -> Eliminaciones -> Registrar comite';

    public const PERMISO_EJECUTAR = 'Gestion de Archivo -> Eliminaciones -> Ejecutar';

    public const DIAS_PUBLICACION = 60;

    public const DIAS_CONCEPTO = 30;

    public const TIPO_NOTIFICACION = 'eliminacion_documental';

    public const EVENTO_PROPUESTA = 'propuesta_eliminacion';

    public const EVENTO_CONCEPTO = 'concepto_eliminacion';

    public const EVENTO_ELIMINACION = 'eliminacion';

    public const ACCION_BITACORA = 'eliminado';

    public const NIVELES_NO_PUBLICOS = ['clasificado', 'reservado'];

    public function __construct(
        private DisposicionFinalService $disposicion,
        private TransferenciaLotesService $lotes,
        private ExpedienteEventosService $eventos,
        private BusinessDaysService $diasHabiles,
    ) {}

    /**
     * Series y subseries con expedientes listos para eliminar que aún no están en un lote, con lo
     * que impide proponerlas.
     */
    public function pendientes(): Collection
    {
        return $this->poblacion()
            ->groupBy(fn (OfiArchivoExpediente $e) => $e->trdRetencion()->id)
            ->map(function (Collection $expedientes, $trdId) {
                $primero = $expedientes->first();
                $trd = $primero->trdRetencion();
                $version = $this->version($trd->version_id);

                return [
                    'trd_id' => (int) $trdId,
                    'cod' => $trd->cod,
                    'nom' => $trd->nom,
                    'dependencia' => trim($primero->dependencia_cod.' '.$primero->dependencia_nom),
                    'version' => $version?->version,
                    'rusd_numero' => $version?->rusd_inscrita ? $version->rusd_numero : null,
                    'expedientes' => $expedientes->count(),
                    'motivo' => $this->motivoRusd($version),
                ];
            })->values();
    }

    /**
     * Propone la eliminación de una serie o subserie y publica su inventario.
     */
    public function proponer(int $trdId, User $usuario): OfiArchivoEliminacionLote
    {
        return DB::transaction(function () use ($trdId, $usuario) {
            $poblacion = $this->poblacion($trdId, true)
                ->sortBy(fn (OfiArchivoExpediente $e) => [$e->fecha_cierre?->timestamp, $e->id])->values();
            if ($poblacion->isEmpty()) {
                throw ValidationException::withMessages(['trd_id' => 'La serie no tiene expedientes listos para eliminar']);
            }

            $primero = $poblacion->first();
            $trd = $primero->trdRetencion();
            $version = $this->version($trd->version_id);
            if ($motivo = $this->motivoRusd($version)) {
                throw ValidationException::withMessages(['trd_id' => $motivo]);
            }
            $prestados = $poblacion->filter(fn (OfiArchivoExpediente $e) => OfiArchivoPrestamo::where('expediente_id', $e->id)->fueraDelArchivo()->exists());
            if ($prestados->isNotEmpty()) {
                throw ValidationException::withMessages(['trd_id' => 'Tienen préstamos sin devolver: '.$prestados->pluck('numero_expediente')->implode(', ')]);
            }

            $publicacion = now();
            $lote = new OfiArchivoEliminacionLote([
                'codigo' => $this->siguienteCodigo($publicacion),
                'trd_id' => $trdId,
                'trd_cod' => (string) $trd->cod,
                'trd_nom' => (string) $trd->nom,
                'dependencia_cod' => $primero->dependencia_cod,
                'dependencia_nom' => $primero->dependencia_nom,
                'trd_version_id' => $version->id,
                'rusd_numero' => $version->rusd_numero,
                'fecha_publicacion' => $publicacion,
                'fin_publicacion' => $this->diasHabiles->calcularVencimiento($publicacion->copy(), self::DIAS_PUBLICACION)->toDateString(),
                'estado' => OfiArchivoEliminacionLote::PUBLICADO,
                'user_id' => $usuario->id,
            ]);
            $items = $poblacion->map(fn (OfiArchivoExpediente $e, int $i) => new OfiArchivoEliminacionItem($this->datosItem($e, $i + 1)));
            $lote->inventario = $this->inventario($lote, $items);
            $lote->hash_inventario = hash('sha256', $lote->inventario);
            $lote->save();
            $lote->items()->saveMany($items);

            foreach ($poblacion as $expediente) {
                $this->eventos->registrar($expediente->id, self::EVENTO_PROPUESTA, [
                    'lote' => $lote->codigo,
                    'fin_publicacion' => $lote->fin_publicacion->toDateString(),
                ]);
            }

            return $lote;
        });
    }

    /** Observación ciudadana al inventario publicado. */
    public function observar(OfiArchivoEliminacionLote $lote, array $datos, ?string $ip): OfiArchivoEliminacionObservacion
    {
        if (! $lote->abiertoAObservaciones()) {
            throw ValidationException::withMessages(['lote' => "La publicación del lote {$lote->codigo} ya no recibe observaciones"]);
        }
        $itemId = null;
        if ($numero = trim((string) ($datos['expediente'] ?? ''))) {
            $itemId = $lote->items()->where('numero_expediente', $numero)->value('id');
            if (! $itemId) {
                throw ValidationException::withMessages(['expediente' => "El expediente {$numero} no está en el inventario del lote"]);
            }
        }

        return OfiArchivoEliminacionObservacion::create([
            'lote_id' => $lote->id,
            'item_id' => $itemId,
            'nombre' => trim($datos['nombre']),
            'identificacion' => $datos['identificacion'] ?? null,
            'correo' => $datos['correo'] ?? null,
            'observacion' => trim($datos['observacion']),
            'ip' => $ip,
        ]);
    }

    /**
     * Cierra las publicaciones vencidas: con observaciones esperan concepto; sin ellas, el comité.
     *
     * @return Collection<int, OfiArchivoEliminacionLote>
     */
    public function cerrarPublicaciones(): Collection
    {
        $cerrados = OfiArchivoEliminacionLote::where('estado', OfiArchivoEliminacionLote::PUBLICADO)
            ->whereDate('fin_publicacion', '<', today())->withCount('observaciones')->get()
            ->each(function (OfiArchivoEliminacionLote $lote) {
                $lote->update($lote->observaciones_count > 0
                    ? [
                        'estado' => OfiArchivoEliminacionLote::EN_CONCEPTO,
                        'fin_concepto' => $this->diasHabiles->calcularVencimiento($lote->fin_publicacion->copy(), self::DIAS_CONCEPTO)->toDateString(),
                    ]
                    : ['estado' => OfiArchivoEliminacionLote::POR_APROBAR]);
            });
        $this->notificar($cerrados);

        return $cerrados;
    }

    /** Concepto sobre las observaciones; solo 'continuar' deja seguir la eliminación. */
    public function emitirConcepto(OfiArchivoEliminacionLote $lote, string $decision, string $concepto, User $usuario): OfiArchivoEliminacionLote
    {
        return DB::transaction(function () use ($lote, $decision, $concepto, $usuario) {
            $lote = $this->bloquear($lote, OfiArchivoEliminacionLote::EN_CONCEPTO);
            $lote->update([
                'decision_concepto' => $decision,
                'concepto' => trim($concepto),
                'concepto_por_id' => $usuario->id,
                'concepto_at' => now(),
                'estado' => $decision === 'continuar' ? OfiArchivoEliminacionLote::POR_APROBAR : OfiArchivoEliminacionLote::SUSPENDIDO,
            ]);
            foreach ($lote->items as $item) {
                $this->eventos->registrar($item->expediente_id, self::EVENTO_CONCEPTO, ['lote' => $lote->codigo, 'decision' => $decision]);
            }

            return $lote;
        });
    }

    /**
     * Aprobación del Comité Institucional: su acta, el método y quienes firman el acta de eliminación.
     *
     * @param  array{acta_comite_numero: string, acta_comite_fecha: string, metodo: string, presidente_id: int, secretario_id: int}  $datos
     */
    public function registrarComite(OfiArchivoEliminacionLote $lote, array $datos, User $usuario): OfiArchivoEliminacionLote
    {
        return DB::transaction(function () use ($lote, $datos, $usuario) {
            $lote = $this->bloquear($lote, OfiArchivoEliminacionLote::POR_APROBAR);
            if ((int) $datos['presidente_id'] === (int) $datos['secretario_id']) {
                throw ValidationException::withMessages(['secretario_id' => 'El presidente y el secretario técnico deben ser personas distintas']);
            }
            foreach (['presidente_id' => 'El presidente', 'secretario_id' => 'El secretario técnico'] as $campo => $quien) {
                if ((int) User::whereKey($datos[$campo])->value('estado') !== 1) {
                    throw ValidationException::withMessages([$campo => "{$quien} no es un usuario activo"]);
                }
            }
            if (Carbon::parse($datos['acta_comite_fecha'])->lt($lote->fin_concepto ?? $lote->fin_publicacion)) {
                throw ValidationException::withMessages(['acta_comite_fecha' => 'El comité no puede aprobar antes de cerrar la publicación y el concepto']);
            }

            $lote->update([
                'acta_comite_numero' => trim($datos['acta_comite_numero']),
                'acta_comite_fecha' => $datos['acta_comite_fecha'],
                'metodo' => $datos['metodo'],
                'presidente_id' => $datos['presidente_id'],
                'secretario_id' => $datos['secretario_id'],
                'comite_por_id' => $usuario->id,
                'comite_at' => now(),
                'estado' => OfiArchivoEliminacionLote::APROBADO,
            ]);

            return $lote;
        });
    }

    /** Firma del presidente o del secretario técnico; con las dos, queda por ejecutar. */
    public function firmar(OfiArchivoEliminacionLote $lote, User $usuario): OfiArchivoEliminacionLote
    {
        return DB::transaction(function () use ($lote, $usuario) {
            $lote = $this->bloquear($lote, OfiArchivoEliminacionLote::APROBADO);
            $parte = match ($usuario->id) {
                (int) $lote->presidente_id => 'presidente',
                (int) $lote->secretario_id => 'secretario',
                default => throw ValidationException::withMessages(['lote' => 'Solo firman el presidente y el secretario técnico designados por el comité']),
            };
            if ($lote->{"firma_{$parte}"}) {
                throw ValidationException::withMessages(['lote' => 'Usted ya firmó esta eliminación']);
            }

            $lote->{"firmado_{$parte}_at"} = now();
            $lote->{"firma_{$parte}"} = self::firma($lote, $parte);
            if ($lote->firma_presidente && $lote->firma_secretario) {
                $lote->estado = OfiArchivoEliminacionLote::POR_EJECUTAR;
            }
            $lote->save();

            return $lote;
        });
    }

    /**
     * Ejecuta la eliminación: marca los expedientes, genera el acta y, confirmado eso, borra los
     * binarios. Un binario que no se pudo borrar queda en la bitácora y se reintenta a mano.
     */
    public function ejecutar(OfiArchivoEliminacionLote $lote, User $usuario): OfiArchivoEliminacionLote
    {
        $borrar = collect();
        $lote = DB::transaction(function () use ($lote, $usuario, &$borrar) {
            $lote = $this->bloquear($lote, OfiArchivoEliminacionLote::POR_EJECUTAR);
            if ((int) $lote->user_id === $usuario->id) {
                throw ValidationException::withMessages(['lote' => 'Quien propuso la eliminación no puede ejecutarla']);
            }
            foreach ([['presidente', $lote->presidente_id], ['secretario', $lote->secretario_id]] as [$parte, $userId]) {
                if (! hash_equals((string) $lote->{"firma_{$parte}"}, self::firma($lote, $parte)) || ! $userId) {
                    throw ValidationException::withMessages(['lote' => 'Las firmas del acta no son válidas']);
                }
            }

            $items = $lote->items()->with('expediente')->get();
            $ids = $items->pluck('expediente_id')->all();
            foreach ($items as $item) {
                $expediente = OfiArchivoExpediente::lockForUpdate()->find($item->expediente_id);
                $motivo = $expediente->estado !== 'en_disposicion'
                    ? "está {$expediente->estado}"
                    : $this->disposicion->motivoNoPuede($expediente, DisposicionFinalService::ELIMINAR);
                if ($motivo) {
                    throw ValidationException::withMessages(['lote' => "El expediente {$expediente->numero_expediente} ya no puede eliminarse: {$motivo}"]);
                }
                if (OfiArchivoPrestamo::where('expediente_id', $expediente->id)->fueraDelArchivo()->exists()) {
                    throw ValidationException::withMessages(['lote' => "El expediente {$expediente->numero_expediente} tiene préstamos sin devolver"]);
                }

                [$propios, $compartidos] = $this->binarios($expediente, $ids);
                $propios = $propios->reject(fn (array $b) => $borrar->contains(fn (array $o) => $o['disk'] === $b['disk'] && $o['path'] === $b['path']));
                $borrar = $borrar->merge($propios->map(fn (array $b) => $b + ['expediente' => $expediente->numero_expediente]));
                $item->update(['archivos_borrados' => $propios->count(), 'archivos_compartidos' => $compartidos]);

                OfiArchivoExpediente::whereKey($expediente->id)->update(['estado' => 'eliminado']);
                $this->eventos->registrar($expediente->id, self::EVENTO_ELIMINACION, [
                    'lote' => $lote->codigo,
                    'acta_comite' => $lote->acta_comite_numero,
                    'metodo' => $lote->metodo,
                    'archivos_borrados' => $propios->count(),
                    'archivos_compartidos' => $compartidos,
                ]);
            }

            $lote->ejecutado_por_id = $usuario->id;
            $lote->ejecutado_at = now();
            $lote->codigo_verificacion = 'EL-'.implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4));
            $lote->acta = $this->acta($lote, $lote->items()->get(), $usuario);
            $lote->hash_acta = hash('sha256', $lote->acta);
            $lote->estado = OfiArchivoEliminacionLote::EJECUTADO;
            $lote->save();

            return $lote;
        });

        foreach ($borrar as $binario) {
            $this->borrar($binario, $lote);
        }

        return $lote;
    }

    /** Descarta un lote que aún no aprobó el comité; sus expedientes quedan libres. */
    public function anular(OfiArchivoEliminacionLote $lote, string $motivo): OfiArchivoEliminacionLote
    {
        return DB::transaction(function () use ($lote, $motivo) {
            $lote = $this->bloquear($lote, [OfiArchivoEliminacionLote::PUBLICADO, OfiArchivoEliminacionLote::EN_CONCEPTO, OfiArchivoEliminacionLote::POR_APROBAR]);
            $lote->update(['estado' => OfiArchivoEliminacionLote::ANULADO, 'motivo_anulacion' => trim($motivo)]);

            return $lote;
        });
    }

    /** Binarios del expediente que se borran y cuántos radicados siguen en otro expediente. */
    public function binarios(OfiArchivoExpediente $expediente, array $enElLote = []): array
    {
        $enElLote = $enElLote ?: [$expediente->id];
        $propios = collect();
        $compartidos = 0;
        $documentos = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)->with('documentable')->get();
        foreach ($documentos as $documento) {
            $ubicacion = ArchivoFolio::ubicacion($documento);
            if (! $ubicacion) {
                continue;
            }
            $otro = OfiArchivoExpedienteDocumento::query()
                ->whereNotIn('expediente_id', $enElLote)
                ->whereHas('expediente', fn ($q) => $q->where('estado', '<>', 'eliminado'))
                ->when($documento->archivo_path,
                    fn ($q) => $q->where('archivo_path', $documento->archivo_path),
                    fn ($q) => $q->where('documentable_type', $documento->documentable_type)->where('documentable_id', $documento->documentable_id))
                ->exists();
            if ($otro) {
                $compartidos++;

                continue;
            }
            $propios->push(['disk' => $ubicacion['disk'], 'path' => $ubicacion['path']]);
        }

        return [$propios->unique(fn (array $b) => $b['disk'].'|'.$b['path'])->values(), $compartidos];
    }

    /** Ítem del inventario: el nombre de un clasificado o reservado no se publica. */
    private function datosItem(OfiArchivoExpediente $expediente, int $orden): array
    {
        $datos = $this->lotes->datosInventario($expediente);

        return [
            'expediente_id' => $expediente->id,
            'orden' => $orden,
            'numero_expediente' => $expediente->numero_expediente,
            'nombre_publico' => in_array($expediente->nivel_acceso, self::NIVELES_NO_PUBLICOS, true)
                ? 'Información '.$expediente->nivel_acceso.' (Ley 1712 de 2014)'
                : mb_substr((string) $expediente->nombre_expediente, 0, 500),
            'fecha_inicial' => $datos['fecha_inicial'],
            'fecha_final' => $datos['fecha_final'],
            'folios' => (int) $datos['folios'],
            'soporte' => $datos['soporte'],
        ];
    }

    /**
     * Expedientes en disposición, con destino eliminar y todo cumplido, que no están en un lote.
     *
     * @return Collection<int, OfiArchivoExpediente>
     */
    private function poblacion(?int $trdId = null, bool $bloquear = false): Collection
    {
        $query = OfiArchivoExpediente::where('estado', 'en_disposicion')
            ->where(fn ($q) => $q->whereNull('destino_disposicion')->orWhere('destino_disposicion', DisposicionFinalService::ELIMINAR))
            ->whereNotExists(fn ($q) => $q->from('ofi_archivo_eliminacion_items as i')
                ->join('ofi_archivo_eliminacion_lotes as l', 'l.id', '=', 'i.lote_id')
                ->whereColumn('i.expediente_id', 'ofi_archivo_expedientes.id')
                ->whereIn('l.estado', OfiArchivoEliminacionLote::ACTIVOS))
            ->orderBy('id');
        if ($bloquear) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(function (OfiArchivoExpediente $expediente) use ($trdId) {
            $trd = $expediente->trdRetencion();

            return $trd && ($trdId === null || (int) $trd->id === $trdId)
                && $this->disposicion->motivoNoPuede($expediente, DisposicionFinalService::ELIMINAR) === null;
        })->values();
    }

    private function version(?int $versionId): ?ClasificacionDocumentalTRDVersion
    {
        return $versionId ? ClasificacionDocumentalTRDVersion::find($versionId) : null;
    }

    private function motivoRusd(?ClasificacionDocumentalTRDVersion $version): ?string
    {
        return match (true) {
            ! $version => 'La serie no pertenece a una versión de TRD aprobada: no se puede verificar su inscripción en el RUSD',
            ! $version->rusd_inscrita => "La TRD versión {$version->version} de {$version->dependencia_nom} no está inscrita en el RUSD",
            default => null,
        };
    }

    /** @param string|array<int, string> $estados */
    private function bloquear(OfiArchivoEliminacionLote $lote, string|array $estados): OfiArchivoEliminacionLote
    {
        $lote = OfiArchivoEliminacionLote::lockForUpdate()->findOrFail($lote->id);
        if (! in_array($lote->estado, (array) $estados, true)) {
            throw ValidationException::withMessages(['lote' => "El lote {$lote->codigo} está {$lote->estado}"]);
        }

        return $lote;
    }

    /** Sobrescribe (en discos locales) y borra un binario; deja la huella en la bitácora. */
    private function borrar(array $binario, OfiArchivoEliminacionLote $lote): void
    {
        $disco = Storage::disk($binario['disk']);
        try {
            $registro = OfiArchivoDepuracionDigital::registrar($binario['disk'], self::ACCION_BITACORA, $binario['path'], null,
                $disco, $binario['path'], "Eliminación {$lote->codigo} ({$binario['expediente']})");
            if (! $disco->exists($binario['path'])) {
                return;
            }
            if (config("filesystems.disks.{$binario['disk']}.driver") === 'local') {
                $ruta = $disco->path($binario['path']);
                $tamano = (int) filesize($ruta);
                $archivo = fopen($ruta, 'r+b');
                for ($escrito = 0; $escrito < $tamano; $escrito += 65536) {
                    fwrite($archivo, random_bytes(min(65536, $tamano - $escrito)));
                }
                fflush($archivo);
                fclose($archivo);
            }
            if (! $disco->delete($binario['path'])) {
                Log::warning("Eliminación {$lote->codigo}: no se pudo borrar {$binario['disk']}:{$binario['path']} (bitácora #{$registro->id})");
            }
        } catch (\Throwable $e) {
            Log::error("Eliminación {$lote->codigo}: error al borrar {$binario['disk']}:{$binario['path']}: {$e->getMessage()}");
        }
    }

    private function siguienteCodigo(Carbon $fecha): string
    {
        $prefijo = 'EL-'.$fecha->year.'-';
        $ultimo = OfiArchivoEliminacionLote::where('codigo', 'like', $prefijo.'%')->lockForUpdate()->max('codigo');

        return $prefijo.str_pad((string) ((int) substr((string) $ultimo, strlen($prefijo)) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @param Collection<int, OfiArchivoEliminacionItem> $items */
    private function inventario(OfiArchivoEliminacionLote $lote, Collection $items): string
    {
        $txt = fn (?string $s) => mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');
        $pdf = new Fpdi('L');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->MultiCell(0, 7, $txt('INVENTARIO DOCUMENTAL PARA ELIMINACIÓN'), 0, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $txt(TransferenciaLotesService::entidad()), 0, 1, 'C');
        $pdf->Ln(3);
        $this->campos($pdf, $txt, [
            'Lote' => $lote->codigo,
            'Oficina productora' => trim($lote->dependencia_cod.' '.$lote->dependencia_nom),
            'Serie / subserie' => trim($lote->trd_cod.' '.$lote->trd_nom),
            'Inscripción RUSD' => (string) $lote->rusd_numero,
            'Publicación' => $lote->fecha_publicacion->format('Y-m-d').' a '.$lote->fin_publicacion->format('Y-m-d').' ('.self::DIAS_PUBLICACION.' días hábiles)',
            'Unidades documentales' => $items->count().' expediente(s), '.$items->sum('folios').' folio(s)',
        ]);

        $pdf->Ln(3);
        $columnas = [[10, 'N.º'], [45, 'Expediente'], [120, 'Nombre'], [28, 'Fecha inicial'], [28, 'Fecha final'], [18, 'Folios'], [28, 'Soporte']];
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        foreach ($columnas as [$ancho, $titulo]) {
            $pdf->Cell($ancho, 7, $txt($titulo), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 7.5);
        foreach ($items->values() as $item) {
            $pdf->Cell(10, 7, (string) $item->orden, 1, 0, 'C');
            $pdf->Cell(45, 7, $txt(mb_substr($item->numero_expediente, 0, 30)), 1, 0);
            $pdf->Cell(120, 7, $txt(mb_substr($item->nombre_publico, 0, 85)), 1, 0);
            $pdf->Cell(28, 7, $this->fecha($item->fecha_inicial), 1, 0, 'C');
            $pdf->Cell(28, 7, $this->fecha($item->fecha_final), 1, 0, 'C');
            $pdf->Cell(18, 7, (string) $item->folios, 1, 0, 'C');
            $pdf->Cell(28, 7, $txt((string) $item->soporte), 1, 1, 'C');
        }
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(0, 5, $txt('Inventario publicado para observaciones de la ciudadanía (Acuerdo AGN 001 de 2024). '
            .'Documento generado automáticamente por el Sistema de Gestión Documental OCOBO.'), 0, 'C');

        return $pdf->Output('S');
    }

    /** @param Collection<int, OfiArchivoEliminacionItem> $items */
    private function acta(OfiArchivoEliminacionLote $lote, Collection $items, User $ejecuta): string
    {
        $txt = fn (?string $s) => mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');
        $presidente = User::find($lote->presidente_id);
        $secretario = User::find($lote->secretario_id);
        $metodo = ['destruccion_fisica' => 'Destrucción física', 'borrado_seguro' => 'Borrado seguro', 'mixto' => 'Destrucción física y borrado seguro'][$lote->metodo] ?? $lote->metodo;

        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->MultiCell(0, 7, $txt('ACTA DE ELIMINACIÓN DOCUMENTAL'), 0, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $txt(TransferenciaLotesService::entidad()), 0, 1, 'C');
        $pdf->Ln(3);
        $this->campos($pdf, $txt, array_filter([
            'Lote' => $lote->codigo,
            'Oficina productora' => trim($lote->dependencia_cod.' '.$lote->dependencia_nom),
            'Serie / subserie' => trim($lote->trd_cod.' '.$lote->trd_nom),
            'Inscripción RUSD' => (string) $lote->rusd_numero,
            'Publicación del inventario' => $lote->fecha_publicacion->format('Y-m-d').' a '.$lote->fin_publicacion->format('Y-m-d'),
            'Inventario (SHA-256)' => $lote->hash_inventario,
            'Concepto sobre observaciones' => $lote->decision_concepto ? $lote->concepto_at->format('Y-m-d').': '.$lote->concepto : 'Sin observaciones de la ciudadanía',
            'Aprobación' => "Comité Institucional de Gestión y Desempeño, acta {$lote->acta_comite_numero} del ".$lote->acta_comite_fecha->format('Y-m-d'),
            'Método de eliminación' => $metodo,
            'Unidades documentales' => $items->count().' expediente(s), '.$items->sum('folios').' folio(s)',
            'Archivos electrónicos' => $items->sum('archivos_borrados').' borrado(s); '.$items->sum('archivos_compartidos').' desvinculado(s) por pertenecer también a otro expediente',
            'Ejecutó' => self::nombre($ejecuta).' el '.$lote->ejecutado_at->format('Y-m-d H:i:s'),
        ]));

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        foreach ([[10, 'N.º'], [42, 'Expediente'], [66, 'Nombre'], [36, 'Fechas extremas'], [14, 'Folios'], [22, 'Archivos']] as [$ancho, $titulo]) {
            $pdf->Cell($ancho, 7, $txt($titulo), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 7.5);
        foreach ($items->values() as $item) {
            $pdf->Cell(10, 7, (string) $item->orden, 1, 0, 'C');
            $pdf->Cell(42, 7, $txt(mb_substr($item->numero_expediente, 0, 28)), 1, 0);
            $pdf->Cell(66, 7, $txt(mb_substr($item->nombre_publico, 0, 46)), 1, 0);
            $pdf->Cell(36, 7, $this->fecha($item->fecha_inicial).' / '.$this->fecha($item->fecha_final), 1, 0, 'C');
            $pdf->Cell(14, 7, (string) $item->folios, 1, 0, 'C');
            $pdf->Cell(22, 7, (string) $item->archivos_borrados, 1, 1, 'C');
        }

        $pdf->Ln(4);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, $txt('Los expedientes relacionados se eliminaron conforme a la Tabla de Retención Documental. Se conservan '
            .'su metadata, su índice electrónico, su historial, este acta y el inventario, que permanecen publicados.'), 0, 'J');
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(95, 6, $txt('Presidente del Comité'), 0, 0);
        $pdf->Cell(95, 6, $txt('Secretario técnico'), 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(95, 6, $txt($presidente ? self::nombre($presidente) : ''), 0, 0);
        $pdf->Cell(95, 6, $txt($secretario ? self::nombre($secretario) : ''), 0, 1);
        $pdf->Cell(95, 6, $txt('Firmado electrónicamente el '.$lote->firmado_presidente_at->format('Y-m-d H:i:s')), 0, 0);
        $pdf->Cell(95, 6, $txt('Firmado electrónicamente el '.$lote->firmado_secretario_at->format('Y-m-d H:i:s')), 0, 1);
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, $txt("Código de verificación: {$lote->codigo_verificacion}"), 0, 1);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, $txt('Documento generado automáticamente por el Sistema de Gestión Documental OCOBO.'), 0, 1, 'C');

        return $pdf->Output('S');
    }

    private function campos(Fpdi $pdf, \Closure $txt, array $datos): void
    {
        foreach ($datos as $campo => $valor) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(52, 6, $txt($campo.':'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 6, $txt((string) $valor), 0, 'L');
        }
    }

    private function fecha(mixed $fecha): string
    {
        return $fecha ? Carbon::parse($fecha)->format('Y-m-d') : '';
    }

    /** Avisa a quien registra el comité de los lotes que esperan concepto o aprobación. */
    private function notificar(Collection $lotes): void
    {
        if ($lotes->isEmpty()) {
            return;
        }
        try {
            $usuarios = User::permission(self::PERMISO_COMITE)->pluck('id')->all();
        } catch (PermissionDoesNotExist) {
            return;
        }
        foreach ($lotes as $lote) {
            $concepto = $lote->estado === OfiArchivoEliminacionLote::EN_CONCEPTO;
            foreach ($usuarios as $userId) {
                Notificacion::create([
                    'user_id' => $userId,
                    'type' => self::TIPO_NOTIFICACION,
                    'title' => $concepto ? 'Eliminación con observaciones' : 'Eliminación por aprobar',
                    'message' => $concepto
                        ? "Terminó la publicación del lote {$lote->codigo} con {$lote->observaciones_count} observación(es). El concepto vence el {$lote->fin_concepto->format('Y-m-d')}."
                        : "Terminó la publicación del lote {$lote->codigo} sin observaciones. Queda para aprobación del comité.",
                    'data' => ['tipo_entidad' => 'eliminacion_lote', 'lote_id' => $lote->id, 'codigo' => $lote->codigo],
                ]);
            }
        }
    }

    private static function firma(OfiArchivoEliminacionLote $lote, string $parte): string
    {
        $datos = [
            $lote->codigo, $lote->hash_inventario, $lote->acta_comite_numero, $lote->acta_comite_fecha?->format('Y-m-d'), $lote->metodo,
            $parte === 'presidente' ? $lote->presidente_id : $lote->secretario_id,
            $lote->{"firmado_{$parte}_at"}?->format('Y-m-d H:i:s'),
        ];

        return hash_hmac('sha256', $parte.'|'.implode('|', $datos), hash_hmac('sha256', 'ocobo-eliminacion-lote', (string) config('app.key')));
    }

    private static function nombre(User $user): string
    {
        return mb_substr(trim($user->nombres.' '.$user->apellidos) ?: 'Usuario #'.$user->id, 0, 255);
    }
}
