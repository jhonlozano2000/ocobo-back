<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Documento colaborativo para Comunicaciones Recibidas en Mi Bandeja.
 *
 * Representa un documento editable en colaboración tiempo real vinculado
 * a un radicado de comunicaciones recibidas.
 *
 * @property int $id
 * @property int $radica_reci_id ID del radicado origen
 * @property int $user_id ID del usuario creador
 * @property string $titulo Título del documento
 * @property string $estado Estado: borrador|en_revision|firmado
 * @property string|null $notas Notas adicionales
 * @property bool $es_publico Si es visible para todos los usuarios
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read RecibidosVentanillaRadicaReci $radicado
 * @property-read User $creador
 * @property-read HasMany<DocumentoUsuario> $usuarios
 * @property-read HasOne<Contenido> $contenido
 * @property-read HasMany<Version> $versiones
 * @property-read HasMany<Comentario> $comentarios
 * @property-read HasMany<Cursor> $cursores
 */
class Documento extends Model
{
    /** Miembros del grupo colaborativo → rol equivalente en el documento (de menor a mayor alcance). */
    public const ROLES_GRUPO = [
        'proyectores' => 'proyector',
        'revisores' => 'responsable',
        'aprobadores' => 'responsable',
        'firmantes' => 'firmante',
    ];

    /** @var string Nombre de la tabla */
    protected $table = 'mi_bandeja_temp_reci_documentos';

    /** @var array<string> Campos asignables en masa */
    protected $fillable = [
        'radica_reci_id',
        // Documento de trabajo para editar una plantilla generada: no aparece en Mis Documentos
        'plantilla_generada_id',
        'user_id',
        'titulo',
        'estado',
        'firmas_retiradas_en',
        'firmas_retiradas_por',
        'notas',
        'es_publico',
        'tamano_papel',
        'orientacion',
        'margenes',
        'configuracion_columnas',
        'configuracion_header',
        'configuracion_footer',
        'configuracion_formato',
        'nombre_archivo_original',
        'archivo_path',
    ];

    /** @var array<string, string> Conversiones de tipos */
    protected $casts = [
        'es_publico' => 'boolean',
        'estado' => 'string',
        'firmas_retiradas_en' => 'datetime',
        'margenes' => 'array',
        'configuracion_columnas' => 'array',
        'configuracion_header' => 'array',
        'configuracion_footer' => 'array',
        'configuracion_formato' => 'array',
    ];

    /**
     * Estados disponibles para el documento.
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        'borrador' => 'Borrador',
        'en_revision' => 'En Revisión',
        'firmado' => 'Firmado',
    ];

    public const ROLES = [
        'firmante' => 'Firmante',
        'responsable' => 'Responsable',
        'proyector' => 'Proyector',
    ];

    public const TAMANOS_PAPEL = [
        'a4' => 'A4 (210 × 297 mm)',
        'carta' => 'Carta (8.5 × 11 in)',
        'legal' => 'Legal (8.5 × 14 in)',
        'oficio' => 'Oficio (8.5 × 13 in)',
    ];

    public const ORIENTACIONES = [
        'vertical' => 'Vertical',
        'horizontal' => 'Horizontal',
    ];

    public const TAMANOS_MM = [
        'a4' => ['ancho' => 210, 'alto' => 297],
        'carta' => ['ancho' => 215.9, 'alto' => 279.4],
        'legal' => ['ancho' => 215.9, 'alto' => 355.6],
        'oficio' => ['ancho' => 215.9, 'alto' => 330.2],
    ];

    public const MARGENES_DEFAULT = [
        'superior' => 25.4,
        'inferior' => 25.4,
        'izquierdo' => 25.4,
        'derecho' => 25.4,
    ];

    /** Plantilla generada que se edita con este documento (si es un documento de trabajo). */
    public function plantillaGenerada(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoPlantillaGenerada::class, 'plantilla_generada_id');
    }

    /**
     * Relación con el radicado origen.
     *
     * @return BelongsTo<VentanillaRadicaReci, Documento>
     */
    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }

    /**
     * Relación con el usuario creador.
     *
     * @return BelongsTo<User, Documento>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con usuarios asignados al documento.
     *
     * @return HasMany<DocumentoUsuario>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(DocumentoUsuario::class, 'documento_id');
    }

    /**
     * Grupos colaborativos (mi_bandeja_temp) que trabajan el documento.
     *
     * @return HasMany<MiBandejaTemp>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(MiBandejaTemp::class, 'documento_id');
    }

    /** El grupo colaborativo vigente del documento (el último activo). */
    public function grupoVigente(): ?MiBandejaTemp
    {
        return $this->grupos()->where('estado_grupo', 'activo')->latest('id')->first();
    }

    /**
     * Rol del usuario en el documento según su grupo colaborativo vigente: proyector, responsable (revisores y
     * aprobadores) o firmante; si tiene varios, el de más alcance.
     */
    public function rolEnGrupo(User $user): ?string
    {
        $grupo = $this->grupoVigente();
        $rol = null;

        foreach ($grupo ? self::ROLES_GRUPO : [] as $relacion => $rolDocumento) {
            if ($grupo->{$relacion}()->where('user_id', $user->id)->exists()) {
                $rol = $rolDocumento;
            }
        }

        return $rol;
    }

    /**
     * Agrega (con OR) los documentos donde el usuario es miembro de un grupo colaborativo no anulado.
     *
     * @param  Builder<Documento>  $query
     */
    public function scopeOrMiembroDeGrupo($query, int $userId)
    {
        return $query->orWhereHas('grupos', fn ($g) => self::filtrarMiembro($g, $userId));
    }

    /** El usuario es miembro de algún grupo colaborativo (no anulado) del documento. */
    public function esMiembroDeGrupo(User $user): bool
    {
        return self::filtrarMiembro($this->grupos(), $user->id)->exists();
    }

    private static function filtrarMiembro($grupos, int $userId)
    {
        return $grupos->where('estado_grupo', '!=', 'anulado')
            ->where(function ($m) use ($userId) {
                foreach (array_keys(self::ROLES_GRUPO) as $relacion) {
                    $m->orWhereHas($relacion, fn ($u) => $u->where('user_id', $userId));
                }
            });
    }

    /**
     * Relación con el contenido Yjs del documento.
     *
     * @return HasOne<Contenido>
     */
    public function contenido(): HasOne
    {
        return $this->hasOne(Contenido::class, 'documento_id');
    }

    /**
     * Relación con las versiones del documento.
     *
     * @return HasMany<Version>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(Version::class, 'documento_id')->orderBy('numero_version', 'desc');
    }

    /**
     * Relación con los comentarios del documento.
     *
     * @return HasMany<Comentario>
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class, 'documento_id');
    }

    /**
     * Relación con los cursores colaborativos.
     *
     * @return HasMany<Cursor>
     */
    public function cursores(): HasMany
    {
        return $this->hasMany(Cursor::class, 'documento_id');
    }

    /**
     * Relación con las sugerencias de cambio (modo revisión).
     *
     * @return HasMany<Sugerencia>
     */
    public function sugerencias(): HasMany
    {
        return $this->hasMany(Sugerencia::class, 'documento_id');
    }

    /**
     * Verifica si un usuario tiene acceso al documento.
     *
     * @param  User  $user  Usuario a verificar
     * @return bool true si tiene acceso
     */
    public function tieneAcceso(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->usuarios()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($this->esMiembroDeGrupo($user)) {
            return true;
        }

        if ($this->es_publico) {
            $depCreador = MiBandejaFiltroService::getDependenciaDelUsuario($this->user_id);
            $depUsuario = MiBandejaFiltroService::getDependenciaDelUsuario($user->id);

            return $depCreador !== null && $depUsuario !== null && $depCreador->id === $depUsuario->id;
        }

        return false;
    }

    /**
     * Verifica si un usuario puede editar el documento.
     *
     * @param  User  $user  Usuario a verificar
     * @return bool true si puede editar
     */
    public function puedeEditar(User $user): bool
    {
        // Firmado = protegido: nadie lo edita hasta retirar las firmas (cualquier cambio invalidaría la firma)
        if ($this->estaProtegido()) {
            return false;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        $rol = $this->rolEnGrupo($user) ?? $this->usuarios()->where('user_id', $user->id)->value('rol');

        return in_array($rol, ['firmante', 'responsable', 'proyector'], true);
    }

    /**
     * Verifica si un usuario puede firmar el documento: un firmante designado o, si no hay, el creador.
     *
     * @param  User  $user  Usuario a verificar
     * @return bool true si puede firmar
     */
    public function puedeFirmar(User $user): bool
    {
        return $this->firmantesRequeridos()->contains($user->id);
    }

    /**
     * Quienes deben firmar: los firmantes del grupo colaborativo vigente (en su orden), los designados en el
     * documento o, si no hay ninguno, el creador.
     *
     * @return Collection<int, int> ids de usuario
     */
    public function firmantesRequeridos(): Collection
    {
        $grupo = $this->grupoVigente();
        $firmantes = $grupo
            ? $grupo->firmantes()->orderBy('orden_firma')->pluck('user_id')
            : collect();

        if ($firmantes->isEmpty()) {
            $firmantes = $this->usuarios()->where('rol', 'firmante')->pluck('user_id');
        }

        return $firmantes->isNotEmpty() ? $firmantes->values() : collect([$this->user_id]);
    }

    /** Firmado con OTP: el contenido queda protegido (solo lectura) hasta que se retiren las firmas. */
    public function estaProtegido(): bool
    {
        return $this->estado === 'firmado';
    }

    /**
     * Firma electrónica del usuario sobre el contenido guardado: sella su hash (quién y cuándo) y protege el
     * documento. La usan la firma del editor y el cumplido del firmante de su grupo colaborativo.
     *
     * @param  string  $verificacion  OTP usado (se guarda enmascarado) o el método, ej. PASSWORD
     *
     * @throws ValidationException si el documento está vacío
     */
    public function firmarContenido(User $user, string $verificacion, ?string $ip = null, ?string $userAgent = null): FirmaEvento
    {
        return DB::transaction(function () use ($user, $verificacion, $ip, $userAgent) {
            // Bloquea el contenido: lo que se firma es exactamente lo guardado en este momento
            $contenido = Contenido::where('documento_id', $this->id)->lockForUpdate()->first();

            if (! $contenido?->hash_contenido) {
                throw ValidationException::withMessages(['documento' => 'El documento está vacío: no hay contenido para firmar.']);
            }

            $fecha = now();
            $firma = FirmaEvento::create([
                'documentable_id' => $this->id,
                'documentable_type' => $this->getMorphClass(),
                'user_id' => $user->id,
                'hash_original' => $contenido->hash_contenido,
                // Sello: el contenido firmado, por quién y cuándo
                'hash_firmado' => hash('sha256', $contenido->hash_contenido.'|'.$user->id.'|'.$fecha->toIso8601String()),
                'otp_utilizado' => ctype_digit($verificacion) ? '***'.substr($verificacion, -3) : $verificacion,
                'ip_address' => $ip,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
                'fecha_firma' => $fecha,
            ]);

            $this->update(['estado' => 'firmado']);

            return $firma;
        });
    }

    /** Eventos de firma electrónica del documento (inmutables), vigentes o no. */
    public function firmas(): MorphMany
    {
        return $this->morphMany(FirmaEvento::class, 'documentable');
    }

    /**
     * Firmas vigentes: las del contenido actual, posteriores al último retiro de firmas.
     *
     * @return MorphMany<FirmaEvento>
     */
    public function firmasVigentes(): MorphMany
    {
        $hash = $this->contenido?->hash_contenido;

        return $this->firmas()
            ->where('hash_original', $hash ?? '')
            ->when($this->firmas_retiradas_en, fn ($q, $desde) => $q->where('fecha_firma', '>', $desde));
    }

    public function getMargenesFinales(): array
    {
        if ($this->margenes) {
            return $this->margenes;
        }

        return self::MARGENES_DEFAULT;
    }

    public function getTamanoPixel(): array
    {
        $tamano = self::TAMANOS_MM[$this->tamano_papel] ?? self::TAMANOS_MM['a4'];
        $mmAPx = 3.7795275591;

        if ($this->orientacion === 'horizontal') {
            return [
                'ancho' => (int) ($tamano['alto'] * $mmAPx),
                'alto' => (int) ($tamano['ancho'] * $mmAPx),
            ];
        }

        return [
            'ancho' => (int) ($tamano['ancho'] * $mmAPx),
            'alto' => (int) ($tamano['alto'] * $mmAPx),
        ];
    }

    public function getConfiguracionPagina(): array
    {
        return [
            'tamano_papel' => $this->tamano_papel ?? 'a4',
            'orientacion' => $this->orientacion ?? 'vertical',
            'margenes' => $this->getMargenesFinales(),
            'columnas' => $this->configuracion_columnas ?? ['numero' => 1],
            'header' => $this->configuracion_header ?? null,
            'footer' => $this->configuracion_footer ?? null,
            'formato' => $this->configuracion_formato ?? null,
        ];
    }
}
