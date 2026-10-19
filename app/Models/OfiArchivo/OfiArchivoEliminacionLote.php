<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * B18 (diseño de expedientes §3.8 E): eliminación documental de una serie o subserie completa.
 * Se publica su inventario, se atienden las observaciones, aprueba el comité, firman el presidente
 * y el secretario técnico y se ejecuta. El ejecutado es inmutable y sigue publicado.
 */
class OfiArchivoEliminacionLote extends Model
{
    public const PUBLICADO = 'publicado';

    public const EN_CONCEPTO = 'en_concepto';

    public const POR_APROBAR = 'por_aprobar';

    public const APROBADO = 'aprobado';

    public const POR_EJECUTAR = 'por_ejecutar';

    public const EJECUTADO = 'ejecutado';

    public const SUSPENDIDO = 'suspendido';

    public const ANULADO = 'anulado';

    /** Mientras está en uno de estos, sus expedientes no entran a otro lote. */
    public const ACTIVOS = [self::PUBLICADO, self::EN_CONCEPTO, self::POR_APROBAR, self::APROBADO, self::POR_EJECUTAR, self::EJECUTADO];

    public const DECISIONES = ['continuar', 'suspender', 'ampliar_retencion', 'cambiar_disposicion'];

    public const METODOS = ['destruccion_fisica', 'borrado_seguro', 'mixto'];

    protected $table = 'ofi_archivo_eliminacion_lotes';

    protected $fillable = [
        'codigo', 'trd_id', 'trd_cod', 'trd_nom', 'dependencia_cod', 'dependencia_nom', 'trd_version_id', 'rusd_numero',
        'inventario', 'hash_inventario', 'fecha_publicacion', 'fin_publicacion', 'fin_concepto', 'decision_concepto',
        'concepto', 'concepto_por_id', 'concepto_at', 'acta_comite_numero', 'acta_comite_fecha', 'metodo',
        'presidente_id', 'secretario_id', 'comite_por_id', 'comite_at', 'firma_presidente', 'firmado_presidente_at',
        'firma_secretario', 'firmado_secretario_at', 'acta', 'hash_acta', 'codigo_verificacion', 'ejecutado_por_id',
        'ejecutado_at', 'motivo_anulacion', 'estado', 'user_id',
    ];

    protected $hidden = ['inventario', 'acta', 'firma_presidente', 'firma_secretario'];

    protected $casts = [
        'fecha_publicacion' => 'datetime',
        'fin_publicacion' => 'date:Y-m-d',
        'fin_concepto' => 'date:Y-m-d',
        'concepto_at' => 'datetime',
        'acta_comite_fecha' => 'date:Y-m-d',
        'comite_at' => 'datetime',
        'firmado_presidente_at' => 'datetime',
        'firmado_secretario_at' => 'datetime',
        'ejecutado_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OfiArchivoEliminacionItem::class, 'lote_id')->orderBy('orden');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(OfiArchivoEliminacionObservacion::class, 'lote_id')->orderBy('id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function conceptoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concepto_por_id');
    }

    public function presidente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presidente_id');
    }

    public function secretario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretario_id');
    }

    public function comitePor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comite_por_id');
    }

    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por_id');
    }

    /** Recibe observaciones hasta el último día de su publicación. */
    public function abiertoAObservaciones(): bool
    {
        return $this->estado === self::PUBLICADO && today()->lte($this->fin_publicacion);
    }
}
