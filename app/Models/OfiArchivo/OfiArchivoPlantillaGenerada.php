<?php

namespace App\Models\OfiArchivo;

use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plantilla generada con el editor (Generador de plantillas o «Guardar como plantilla» en Mis Documentos).
 *
 * @property int $id
 * @property int|null $user_id autor
 * @property int|null $tipo_id detalle de la lista «Tipos de plantilla» (TipPlanti)
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $institucional true: la ve todo el mundo; false: solo su autor
 * @property bool $activo
 * @property array $contenido JSON de TipTap con nodos «variable»
 * @property array|null $configuracion configuración de página (tamaño, márgenes, encabezado, pie…)
 */
class OfiArchivoPlantillaGenerada extends Model
{
    use SoftDeletes;

    protected $table = 'ofi_archivo_plantillas_generadas';

    protected $fillable = [
        'user_id',
        'tipo_id',
        'nombre',
        'descripcion',
        'institucional',
        'activo',
        'contenido',
        'configuracion',
        'user_actualiza_id',
    ];

    protected $casts = [
        'institucional' => 'boolean',
        'activo' => 'boolean',
        'contenido' => 'array',
        'configuracion' => 'array',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ConfigListaDetalle::class, 'tipo_id');
    }

    /** Documentos de trabajo con los que se edita en el editor (uno por usuario). */
    public function documentosTrabajo(): HasMany
    {
        return $this->hasMany(Documento::class, 'plantilla_generada_id');
    }

    /** Las que puede usar un usuario: las institucionales activas y sus personales. */
    public function scopeVisiblesPara(Builder $q, int $userId): Builder
    {
        return $q->where(fn ($w) => $w->where(fn ($i) => $i->where('institucional', true)->where('activo', true))
            ->orWhere(fn ($p) => $p->where('institucional', false)->where('user_id', $userId)));
    }
}
