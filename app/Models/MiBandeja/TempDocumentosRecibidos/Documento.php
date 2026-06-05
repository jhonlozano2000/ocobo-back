<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\DocumentoUsuario;
use App\Models\MiBandeja\TempDocumentosRecibidos\Sugerencia;
use App\Models\MiBandeja\TempDocumentosRecibidos\Version;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
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
        'user_id',
        'titulo',
        'estado',
        'notas',
        'es_publico',
        'tamano_papel',
        'orientacion',
        'margenes',
        'configuracion_columnas',
        'configuracion_header',
        'configuracion_footer',
        'nombre_archivo_original',
        'archivo_path',
    ];

    /** @var array<string, string> Conversiones de tipos */
    protected $casts = [
        'es_publico' => 'boolean',
        'estado' => 'string',
        'margenes' => 'array',
        'configuracion_columnas' => 'array',
        'configuracion_header' => 'array',
        'configuracion_footer' => 'array',
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
     * @param User $user Usuario a verificar
     * @return bool true si tiene acceso
     */
    public function tieneAcceso(User $user): bool
    {
        if ($this->es_publico) {
            return true;
        }

        return $this->usuarios()->where('user_id', $user->id)->exists()
            || $this->user_id === $user->id;
    }

    /**
     * Verifica si un usuario puede editar el documento.
     *
     * @param User $user Usuario a verificar
     * @return bool true si puede editar
     */
    public function puedeEditar(User $user): bool
    {
        $rol = $this->usuarios()->where('user_id', $user->id)->first();

        if (!$rol) {
            return $this->user_id === $user->id;
        }

        return in_array($rol->rol, ['firmante', 'responsable', 'proyector']);
    }

    /**
     * Verifica si un usuario puede firmar el documento.
     *
     * @param User $user Usuario a verificar
     * @return bool true si puede firmar
     */
    public function puedeFirmar(User $user): bool
    {
        $rol = $this->usuarios()->where('user_id', $user->id)->first();

        return $rol && $rol->rol === 'firmante';
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
        ];
    }
}
