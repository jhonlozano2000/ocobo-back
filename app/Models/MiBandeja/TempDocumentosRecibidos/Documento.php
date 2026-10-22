<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

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

        $rol = $this->usuarios()->where('user_id', $user->id)->first();

        if (! $rol) {
            return $this->user_id === $user->id;
        }

        return in_array($rol->rol, ['firmante', 'responsable', 'proyector']);
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
     * Quienes deben firmar: los firmantes designados o, si no hay ninguno, el creador.
     *
     * @return Collection<int, int> ids de usuario
     */
    public function firmantesRequeridos(): Collection
    {
        $firmantes = $this->usuarios()->where('rol', 'firmante')->pluck('user_id');

        return $firmantes->isNotEmpty() ? $firmantes->values() : collect([$this->user_id]);
    }

    /** Firmado con OTP: el contenido queda protegido (solo lectura) hasta que se retiren las firmas. */
    public function estaProtegido(): bool
    {
        return $this->estado === 'firmado';
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
