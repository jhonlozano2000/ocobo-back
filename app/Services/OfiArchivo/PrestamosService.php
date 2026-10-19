<?php

namespace App\Services\OfiArchivo;

use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteTomo;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Préstamo y consulta (diseño de expedientes §3.10).
 *
 * - Solicitud: cualquier usuario con el permiso Solicitar pide el expediente o un tomo, en sala,
 *   físico o electrónico, con motivo y fecha de devolución dentro del plazo máximo de la entidad.
 * - Aprobación: el responsable del archivo de la fase donde está el expediente (permiso Aprobar y,
 *   en gestión, de la dependencia del expediente; en central e histórico, del archivo central).
 *   Nadie aprueba su propia solicitud. Los clasificados y reservados exigen además el permiso
 *   Ver reservados, el fundamento de la autorización y decir si se entrega la versión pública.
 * - Sala y físico: el archivo registra la entrega y la devolución con los folios contados; si no
 *   coinciden, el préstamo queda marcado y se avisa a quien aprobó y a quien entregó.
 * - Electrónico: al aprobarse empieza el acceso de solo lectura, que expira en la fecha de
 *   devolución.
 */
class PrestamosService
{
    public const PERMISO_VER = 'Gestion de Archivo -> Prestamos -> Ver';

    public const PERMISO_SOLICITAR = 'Gestion de Archivo -> Prestamos -> Solicitar';

    /** Anterior a la solicitud: registraba el préstamo directamente; ahora también solicita. */
    public const PERMISO_CREAR = 'Gestion de Archivo -> Prestamos -> Crear';

    public const PERMISO_APROBAR = 'Gestion de Archivo -> Prestamos -> Aprobar';

    /** Entregar y recibir el expediente físico en el archivo. */
    public const PERMISO_DEVOLVER = 'Gestion de Archivo -> Prestamos -> Devolver';

    public const PERMISO_VER_RESERVADOS = 'Gestion de Archivo -> Expedientes -> Ver reservados';

    public const ESTADOS_PRESTABLES = ['abierto', 'cerrado', 'en_disposicion', 'transferido'];

    public const TYPE_RESPUESTA = 'prestamo_respuesta';

    public const TYPE_FOLIOS = 'prestamo_folios_no_coinciden';

    /** @var array<int, array<string, mixed>> contexto de permisos por usuario, para no repetir consultas */
    private array $contextos = [];

    /**
     * @param  array{expediente_id: int, tomo_id?: ?int, modalidad: string, motivo: string, fecha_devolucion_esperada: string, dependencia_destino?: ?string, observaciones?: ?string}  $datos
     */
    public function solicitar(array $datos, User $solicitante): OfiArchivoPrestamo
    {
        return DB::transaction(function () use ($datos, $solicitante) {
            $expediente = OfiArchivoExpediente::lockForUpdate()->findOrFail($datos['expediente_id']);
            $modalidad = $datos['modalidad'];
            $tomo = $this->tomo($expediente, $datos['tomo_id'] ?? null, $modalidad);

            $this->validarPrestable($expediente, $modalidad);
            $this->validarPlazo($datos['fecha_devolucion_esperada']);
            if ($modalidad !== 'electronico') {
                $this->validarDisponible($expediente, $tomo);
            }

            return OfiArchivoPrestamo::create([
                'expediente_id' => $expediente->id,
                'tomo_id' => $tomo?->id,
                'modalidad' => $modalidad,
                'motivo' => $datos['motivo'],
                'solicitante_id' => $solicitante->id,
                'dependencia_destino' => $datos['dependencia_destino'] ?? null,
                'fecha_devolucion_esperada' => $datos['fecha_devolucion_esperada'],
                'observaciones' => $datos['observaciones'] ?? null,
                'estado' => 'solicitado',
                'usuario_registro_id' => $solicitante->id,
            ]);
        });
    }

    /**
     * @param  array{fecha_devolucion_esperada?: ?string, fundamento_autorizacion?: ?string, version_publica?: ?bool}  $datos
     */
    public function aprobar(OfiArchivoPrestamo $prestamo, array $datos, User $aprobador): OfiArchivoPrestamo
    {
        return DB::transaction(function () use ($prestamo, $datos, $aprobador) {
            $prestamo = $this->bloquear($prestamo, ['solicitado'], 'Solo se aprueba una solicitud pendiente');
            $expediente = $prestamo->expediente;
            $this->autorizarAprobacion($prestamo, $aprobador);

            $reservado = $this->esReservado($expediente);
            if ($reservado) {
                if (! $this->contexto($aprobador)['ver_reservados']) {
                    throw new AuthorizationException("El expediente es {$expediente->nivel_acceso}: solo lo presta quien puede ver reservados");
                }
                if (mb_strlen(trim((string) ($datos['fundamento_autorizacion'] ?? ''))) < 10) {
                    throw ValidationException::withMessages(['fundamento_autorizacion' => "Explique por qué autoriza prestar un expediente {$expediente->nivel_acceso} (mínimo 10 caracteres)"]);
                }
            }

            $fecha = $datos['fecha_devolucion_esperada'] ?? null;
            if ($fecha) {
                $this->validarPlazo($fecha, $prestamo->created_at);
            }

            $this->validarPrestable($expediente, $prestamo->modalidad);
            if ($prestamo->modalidad !== 'electronico') {
                $this->validarDisponible($expediente, $prestamo->tomo, $prestamo->id);
            }

            $electronico = $prestamo->modalidad === 'electronico';
            $versionPublica = $reservado && (bool) ($datos['version_publica'] ?? false);
            $documentos = $electronico && $versionPublica ? $this->documentosAutorizados($expediente, $datos['documentos_ids'] ?? []) : null;
            $prestamo->update(array_filter([
                'estado' => $electronico ? 'prestado' : 'aprobado',
                'fecha_prestamo' => $electronico ? now() : null,
                'aprobador_id' => $aprobador->id,
                'fecha_aprobacion' => now(),
                'fecha_devolucion_esperada' => $fecha,
                'fundamento_autorizacion' => $reservado ? trim($datos['fundamento_autorizacion']) : null,
            ], fn ($v) => $v !== null) + ['version_publica' => $versionPublica, 'documentos_autorizados' => $documentos]);

            $this->avisarSolicitante($prestamo, $electronico
                ? 'Su acceso electrónico al expediente %s fue aprobado. Puede consultarlo hasta el %s.'
                : 'Su préstamo del expediente %s fue aprobado. Recójalo en el archivo; debe devolverlo el %s.');

            return $prestamo;
        });
    }

    public function rechazar(OfiArchivoPrestamo $prestamo, string $motivo, User $aprobador): OfiArchivoPrestamo
    {
        return DB::transaction(function () use ($prestamo, $motivo, $aprobador) {
            $prestamo = $this->bloquear($prestamo, ['solicitado'], 'Solo se rechaza una solicitud pendiente');
            $this->autorizarAprobacion($prestamo, $aprobador);

            $prestamo->update([
                'estado' => 'rechazado',
                'aprobador_id' => $aprobador->id,
                'fecha_aprobacion' => now(),
                'motivo_rechazo' => trim($motivo),
            ]);
            $this->avisarSolicitante($prestamo, 'Su solicitud de préstamo del expediente %s fue rechazada: '.trim($motivo));

            return $prestamo;
        });
    }

    /** Quien solicitó retira la solicitud mientras no se le haya entregado nada. */
    public function cancelar(OfiArchivoPrestamo $prestamo, User $usuario): OfiArchivoPrestamo
    {
        return DB::transaction(function () use ($prestamo, $usuario) {
            $prestamo = $this->bloquear($prestamo, OfiArchivoPrestamo::ESTADOS_PENDIENTES, 'Solo se cancela una solicitud que aún no se ha entregado');
            if ((int) $prestamo->solicitante_id !== (int) $usuario->id) {
                throw new AuthorizationException('Solo quien solicitó el préstamo puede cancelarlo');
            }

            $prestamo->update(['estado' => 'cancelado']);

            return $prestamo;
        });
    }

    /** El archivo entrega el expediente o el tomo aprobado y registra los folios que salen. */
    public function entregar(OfiArchivoPrestamo $prestamo, int $folios, User $usuario): OfiArchivoPrestamo
    {
        return DB::transaction(function () use ($prestamo, $folios, $usuario) {
            $prestamo = $this->bloquear($prestamo, ['aprobado'], 'Solo se entrega un préstamo aprobado');
            $this->autorizarArchivo($prestamo, $usuario, $this->contexto($usuario)['devolver'], 'entregar');
            $this->validarDisponible($prestamo->expediente, $prestamo->tomo, $prestamo->id);

            $prestamo->update([
                'estado' => 'prestado',
                'fecha_prestamo' => now(),
                'folios_entrega' => $folios,
                'entregado_por_id' => $usuario->id,
            ]);

            return $prestamo;
        });
    }

    /**
     * El archivo recibe el expediente con los folios contados; el acceso electrónico se puede
     * terminar antes de que expire. Préstamos anteriores a la solicitud no tienen folios de entrega.
     */
    public function devolver(OfiArchivoPrestamo $prestamo, ?int $folios, User $usuario): OfiArchivoPrestamo
    {
        return DB::transaction(function () use ($prestamo, $folios, $usuario) {
            $prestamo = $this->bloquear($prestamo, OfiArchivoPrestamo::ESTADOS_ACTIVOS, 'Este préstamo ya fue devuelto o no se ha entregado');
            $this->autorizarArchivo($prestamo, $usuario, $this->contexto($usuario)['devolver'], 'recibir');

            $fisico = $prestamo->modalidad !== 'electronico';
            if ($fisico && $prestamo->folios_entrega !== null && $folios === null) {
                throw ValidationException::withMessages(['folios' => 'Cuente los folios que se devuelven']);
            }

            $noCoinciden = $fisico && $prestamo->folios_entrega !== null && $folios !== $prestamo->folios_entrega;
            $prestamo->update([
                'estado' => 'devuelto',
                'fecha_devolucion_real' => now(),
                'folios_devolucion' => $fisico ? $folios : null,
                'recibido_por_id' => $usuario->id,
                'folios_no_coinciden' => $noCoinciden,
            ]);

            if ($noCoinciden) {
                $this->avisarFolios($prestamo);
            }

            return $prestamo;
        });
    }

    /** Lo que el usuario puede hacer con el préstamo, para que la vista no lo deduzca. */
    public function acciones(OfiArchivoPrestamo $prestamo, User $usuario): array
    {
        $ctx = $this->contexto($usuario);
        $expediente = $prestamo->expediente;
        $responsable = $expediente && $this->esDelArchivo($expediente, $ctx);
        $propio = (int) $prestamo->solicitante_id === (int) $usuario->id;
        $electronico = $prestamo->modalidad === 'electronico';

        return [
            'aprobar' => $prestamo->estado === 'solicitado' && $ctx['aprobar'] && $responsable && ! $propio
                && (! $this->esReservado($expediente) || $ctx['ver_reservados']),
            'rechazar' => $prestamo->estado === 'solicitado' && $ctx['aprobar'] && $responsable && ! $propio,
            'cancelar' => $propio && in_array($prestamo->estado, OfiArchivoPrestamo::ESTADOS_PENDIENTES, true),
            'entregar' => $prestamo->estado === 'aprobado' && $ctx['devolver'] && $responsable,
            'devolver' => in_array($prestamo->estado, OfiArchivoPrestamo::ESTADOS_ACTIVOS, true) && $ctx['devolver'] && $responsable,
            // Préstamo electrónico: consulta en línea del solicitante y traza de accesos
            'consultar' => $electronico && $propio && $prestamo->estado === 'prestado' && (bool) $prestamo->fecha_devolucion_esperada?->isFuture(),
            'accesos' => $electronico && $prestamo->fecha_prestamo !== null
                && ($propio || (int) $prestamo->aprobador_id === (int) $usuario->id || self::tiene($usuario, self::PERMISO_VER)),
        ];
    }

    /** Préstamos que el usuario atiende como responsable del archivo: por aprobar, entregar o recibir. */
    public function porAtender(Builder $query, User $usuario): Builder
    {
        $ctx = $this->contexto($usuario);
        $estados = array_merge(
            $ctx['aprobar'] ? ['solicitado'] : [],
            $ctx['devolver'] ? ['aprobado', ...OfiArchivoPrestamo::ESTADOS_ACTIVOS] : [],
        );

        return $query->whereIn('estado', $estados)
            ->where('solicitante_id', '!=', $usuario->id)
            ->when(! $ctx['ver_todos'], fn ($q) => $q->whereHas('expediente', fn ($q) => $q->where(function ($q) use ($ctx) {
                $q->where(fn ($q) => $q->where('fase', 'gestion')->whereIn('dependencia_id', $ctx['dependencias'] ?: [0]));
                if ($ctx['central']) {
                    $q->orWhere('fase', '!=', 'gestion');
                }
            })));
    }

    public function puedeSolicitar(User $usuario): bool
    {
        return self::tiene($usuario, self::PERMISO_SOLICITAR) || self::tiene($usuario, self::PERMISO_CREAR);
    }

    public static function tiene(User $usuario, string $permiso): bool
    {
        try {
            return $usuario->hasPermissionTo($permiso);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function contexto(User $usuario): array
    {
        return $this->contextos[$usuario->id] ??= [
            'aprobar' => self::tiene($usuario, self::PERMISO_APROBAR),
            'devolver' => self::tiene($usuario, self::PERMISO_DEVOLVER),
            'ver_reservados' => self::tiene($usuario, self::PERMISO_VER_RESERVADOS),
            'ver_todos' => OfiArchivoExpediente::puedeVerTodos($usuario),
            'central' => Gate::forUser($usuario)->allows('Jefe de Archivo') || OfiArchivoExpediente::puedeVerTodos($usuario),
            'dependencias' => OfiArchivoExpediente::dependenciasDelUsuario($usuario),
        ];
    }

    /**
     * Responsable del archivo de la fase: en gestión, de la dependencia del expediente (o quien ve
     * todos); en central e histórico, el archivo central.
     */
    private function esDelArchivo(OfiArchivoExpediente $expediente, array $ctx): bool
    {
        if (($expediente->fase ?? 'gestion') !== 'gestion') {
            return $ctx['central'];
        }

        return $ctx['ver_todos'] || in_array((int) $expediente->dependencia_id, $ctx['dependencias'], true);
    }

    private function autorizarAprobacion(OfiArchivoPrestamo $prestamo, User $aprobador): void
    {
        $this->autorizarArchivo($prestamo, $aprobador, $this->contexto($aprobador)['aprobar'], 'aprobar');
        if ((int) $prestamo->solicitante_id === (int) $aprobador->id) {
            throw new AuthorizationException('Nadie aprueba su propia solicitud de préstamo');
        }
    }

    private function autorizarArchivo(OfiArchivoPrestamo $prestamo, User $usuario, bool $permiso, string $accion): void
    {
        $archivo = ($prestamo->expediente->fase ?? 'gestion') === 'gestion' ? 'de gestión de la dependencia' : 'central';
        if (! $permiso || ! $this->esDelArchivo($prestamo->expediente, $this->contexto($usuario))) {
            throw new AuthorizationException("Solo el responsable del archivo {$archivo} puede {$accion} este préstamo");
        }
    }

    private function bloquear(OfiArchivoPrestamo $prestamo, array $estados, string $mensaje): OfiArchivoPrestamo
    {
        $prestamo = OfiArchivoPrestamo::lockForUpdate()->with(['expediente', 'tomo'])->findOrFail($prestamo->id);
        if (! in_array($prestamo->estado, $estados, true)) {
            throw ValidationException::withMessages(['estado' => $mensaje]);
        }

        return $prestamo;
    }

    private function tomo(OfiArchivoExpediente $expediente, ?int $tomoId, string $modalidad): ?OfiArchivoExpedienteTomo
    {
        if (! $tomoId) {
            return null;
        }
        if ($modalidad === 'electronico') {
            throw ValidationException::withMessages(['tomo_id' => 'El acceso electrónico es al expediente completo, no a un tomo']);
        }

        $tomo = OfiArchivoExpedienteTomo::where('expediente_id', $expediente->id)->find($tomoId);
        if (! $tomo) {
            throw ValidationException::withMessages(['tomo_id' => 'El tomo no es de este expediente']);
        }

        return $tomo;
    }

    private function validarPrestable(OfiArchivoExpediente $expediente, string $modalidad): void
    {
        if ($expediente->estado === 'eliminado') {
            throw ValidationException::withMessages(['expediente_id' => 'No se puede prestar un expediente que ha sido eliminado.']);
        }
        if (! in_array($expediente->estado, self::ESTADOS_PRESTABLES, true)) {
            throw ValidationException::withMessages(['expediente_id' => "No se puede prestar un expediente en estado '{$expediente->estado}'."]);
        }
        if ($modalidad === 'electronico' && $expediente->soporte === 'fisico') {
            throw ValidationException::withMessages(['modalidad' => 'El expediente es físico: no tiene documentos electrónicos que consultar']);
        }
        if ($modalidad !== 'electronico' && $expediente->soporte === 'electronico') {
            throw ValidationException::withMessages(['modalidad' => 'El expediente es electrónico: solicite el acceso electrónico']);
        }
    }

    private function validarPlazo(string $fecha, $desde = null): void
    {
        $dias = ConfiguracionArchivo::prestamoPlazoMaximo();
        $limite = ($desde ? $desde->copy() : now())->addDays($dias)->endOfDay();
        $devolucion = Carbon::parse($fecha);

        if ($devolucion->lte(now())) {
            throw ValidationException::withMessages(['fecha_devolucion_esperada' => 'La fecha de devolución debe ser posterior a hoy']);
        }
        if ($devolucion->gt($limite)) {
            throw ValidationException::withMessages(['fecha_devolucion_esperada' => "El plazo máximo de un préstamo es de {$dias} días (hasta el {$limite->toDateString()})"]);
        }
    }

    /**
     * Sala y físico: el expediente o el tomo no puede estar entregado ni reservado para otra
     * entrega. Prestar el expediente completo choca con cualquier tomo prestado, y al revés.
     */
    private function validarDisponible(OfiArchivoExpediente $expediente, ?OfiArchivoExpedienteTomo $tomo, ?int $excepto = null): void
    {
        $choque = OfiArchivoPrestamo::where('expediente_id', $expediente->id)
            ->whereIn('estado', ['aprobado', ...OfiArchivoPrestamo::ESTADOS_ACTIVOS])
            ->where('modalidad', '!=', 'electronico')
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))
            ->when($tomo, fn ($q) => $q->where(fn ($q) => $q->whereNull('tomo_id')->orWhere('tomo_id', $tomo->id)))
            ->with('tomo:id,numero')
            ->first();

        if ($choque) {
            $que = $choque->tomo ? "El tomo {$choque->tomo->numero} del expediente" : 'El expediente';
            $como = $choque->estado === 'aprobado' ? 'tiene un préstamo aprobado por entregar' : 'ya se encuentra en préstamo activo';
            throw ValidationException::withMessages(['expediente_id' => "{$que} {$como}."]);
        }
    }

    /**
     * Versión pública del préstamo electrónico: los documentos activos del expediente que el
     * solicitante puede consultar.
     *
     * @return int[]
     */
    private function documentosAutorizados(OfiArchivoExpediente $expediente, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            throw ValidationException::withMessages(['documentos_ids' => 'Elija los documentos que forman la versión pública']);
        }
        $validos = $expediente->documentos()->where('activo', true)->whereIn('id', $ids)->pluck('id')->all();
        if (count($validos) !== count($ids)) {
            throw ValidationException::withMessages(['documentos_ids' => 'Algunos documentos no son del expediente o fueron retirados']);
        }
        sort($validos);

        return $validos;
    }

    private function esReservado(?OfiArchivoExpediente $expediente): bool
    {
        return in_array($expediente?->nivel_acceso, ['clasificado', 'reservado'], true);
    }

    private function avisarSolicitante(OfiArchivoPrestamo $prestamo, string $plantilla): void
    {
        $numero = $prestamo->expediente->numero_expediente;
        Notificacion::create([
            'user_id' => $prestamo->solicitante_id,
            'type' => self::TYPE_RESPUESTA,
            'title' => $prestamo->estado === 'rechazado' ? 'Préstamo rechazado' : 'Préstamo aprobado',
            'message' => sprintf($plantilla, $numero, $prestamo->fecha_devolucion_esperada->toDateString()),
            'data' => [
                'prestamo_id' => $prestamo->id,
                'expediente_id' => $prestamo->expediente_id,
                'numero_expediente' => $numero,
                'tipo_entidad' => 'expediente',
                'estado' => $prestamo->estado,
            ],
        ]);
    }

    private function avisarFolios(OfiArchivoPrestamo $prestamo): void
    {
        $numero = $prestamo->expediente->numero_expediente;
        $que = $prestamo->tomo ? "el tomo {$prestamo->tomo->numero} del expediente {$numero}" : "el expediente {$numero}";
        $destinatarios = array_unique(array_filter([$prestamo->aprobador_id, $prestamo->entregado_por_id, $prestamo->recibido_por_id]));

        foreach ($destinatarios as $userId) {
            Notificacion::create([
                'user_id' => $userId,
                'type' => self::TYPE_FOLIOS,
                'title' => 'Folios del préstamo no coinciden',
                'message' => "Se entregó {$que} con {$prestamo->folios_entrega} folios y se devolvió con {$prestamo->folios_devolucion}. Revise el expediente.",
                'data' => [
                    'prestamo_id' => $prestamo->id,
                    'expediente_id' => $prestamo->expediente_id,
                    'numero_expediente' => $numero,
                    'tipo_entidad' => 'expediente',
                    'folios_entrega' => $prestamo->folios_entrega,
                    'folios_devolucion' => $prestamo->folios_devolucion,
                ],
            ]);
        }
    }
}
