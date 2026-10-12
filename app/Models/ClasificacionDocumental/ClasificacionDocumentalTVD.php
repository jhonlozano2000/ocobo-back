<?php

namespace App\Models\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionDocumentalTVD extends Model
{
    use HasFactory;

    protected $table = 'clasificacion_documental_tvd';

    protected $fillable = [
        'tipo',
        'cod',
        'nom',
        'descripcion',
        'soporte',
        'gestion',
        'central',
        'total_anios',
        'disposicion_final',
        'procedimiento',
        'parent',
        'periodo_id',
        'unidad_id',
        'user_register',
        'estado',
    ];

    protected $casts = [
        'gestion' => 'integer',
        'central' => 'integer',
        'total_anios' => 'integer',
        'estado' => 'boolean',
    ];

    /**
     * Relación con los elementos hijos.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent')->with('children');
    }

    /**
     * Relación con el elemento padre.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent');
    }

    /**
     * Período del fondo acumulado al que pertenece (M11-02); la aprobación vive en el período.
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(FondoAcumuladoPeriodo::class, 'periodo_id');
    }

    /**
     * Unidad administrativa histórica que produjo la serie.
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(FondoAcumuladoUnidad::class, 'unidad_id');
    }

    /**
     * Relación con el usuario que registró el elemento.
     */
    public function userRegister(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_register');
    }

    // ==================== SCOPES ====================

    /**
     * Scope para filtrar por tipo de elemento.
     */
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para filtrar solo series documentales.
     */
    public function scopeSerieDocumental($query)
    {
        return $query->where('tipo', 'SerieDocumental');
    }

    /**
     * Scope para filtrar solo subseries documentales.
     */
    public function scopeSubSerieDocumental($query)
    {
        return $query->where('tipo', 'SubSerieDocumental');
    }

    /**
     * Scope para filtrar elementos raíz (sin padre).
     */
    public function scopeRaiz($query)
    {
        return $query->whereNull('parent');
    }

    /**
     * Scope para filtrar por unidad administrativa histórica.
     */
    public function scopePorUnidad($query, int $unidadId)
    {
        return $query->where('unidad_id', $unidadId);
    }

    /**
     * Scope para buscar por código o nombre.
     */
    public function scopeBuscar($query, string $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('cod', 'like', "%{$termino}%")
                ->orWhere('nom', 'like', "%{$termino}%");
        });
    }

    // ==================== MÉTODOS HELPER ====================

    /**
     * Verifica si el elemento es una serie documental.
     */
    public function isSerieDocumental(): bool
    {
        return $this->tipo === 'SerieDocumental';
    }

    /**
     * Verifica si el elemento es una subserie documental.
     */
    public function isSubSerieDocumental(): bool
    {
        return $this->tipo === 'SubSerieDocumental';
    }

    /**
     * Verifica si el elemento es raíz (no tiene padre).
     */
    public function isRaiz(): bool
    {
        return is_null($this->parent);
    }

    /**
     * Verifica si el elemento tiene hijos.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Obtiene la jerarquía completa del elemento.
     */
    public function getJerarquia(): array
    {
        $jerarquia = [];
        $elemento = $this;

        while ($elemento) {
            array_unshift($jerarquia, [
                'id' => $elemento->id,
                'tipo' => $elemento->tipo,
                'cod' => $elemento->cod,
                'nom' => $elemento->nom,
            ]);
            $elemento = $elemento->parent;
        }

        return $jerarquia;
    }

    /**
     * Obtiene el código completo de la jerarquía.
     */
    public function getCodigoCompleto(): string
    {
        $jerarquia = $this->getJerarquia();

        return implode('.', array_column($jerarquia, 'cod'));
    }

    /**
     * Obtiene el nombre completo de la jerarquía.
     */
    public function getNombreCompleto(): string
    {
        $jerarquia = $this->getJerarquia();

        return implode(' > ', array_column($jerarquia, 'nom'));
    }

    /**
     * Calcula los años totales de retención.
     */
    public function getAniosRetencion(): int
    {
        return ($this->gestion ?? 0) + ($this->central ?? 0);
    }

    /**
     * Verifica si el elemento puede ser eliminado.
     */
    public function puedeEliminar(): bool
    {
        return ! $this->hasChildren();
    }
}
