<?php

namespace App\Models\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionDocumentalTRD extends Model
{
    use HasFactory;

    protected $table = 'clasificacion_documental_trd';

    protected $fillable = [
        'tipo',
        'cod',
        'nom',
        'a_g',
        'a_c',
        'ct',
        'e',
        'm_d',
        's',
        'procedimiento',
        'parent',
        'dependencia_id',
        'version_id',
        'user_register',
        'estado',
        'version',
        'estado_version'
    ];

    protected $casts = [
        'ct' => 'boolean',
        'e' => 'boolean',
        'm_d' => 'boolean',
        's' => 'boolean',
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
     * Relación con la dependencia.
     */
    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_id');
    }

    /**
     * Relación con el usuario que registró el elemento.
     */
    public function userRegister(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_register');
    }

    /**
     * Relación con la versión TRD.
     */
    public function versionTRD(): BelongsTo
    {
        return $this->belongsTo(ClasificacionDocumentalTRDVersion::class, 'version_id');
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
     * Scope para filtrar solo series.
     */
    public function scopeSeries($query)
    {
        return $query->where('tipo', 'Serie')->whereNull('parent');
    }

    /**
     * Scope para filtrar solo subseries.
     */
    public function scopeSubSeries($query)
    {
        return $query->where('tipo', 'SubSerie');
    }

    /**
     * Scope para filtrar solo tipos de documento.
     */
    public function scopeTiposDocumento($query)
    {
        return $query->where('tipo', 'TipoDocumento');
    }

    /**
     * Scope para filtrar elementos raíz (sin padre).
     */
    public function scopeRaiz($query)
    {
        return $query->whereNull('parent');
    }

    /**
     * Scope para filtrar por dependencia.
     */
    public function scopePorDependencia($query, int $dependenciaId)
    {
        return $query->where('dependencia_id', $dependenciaId);
    }

    /**
     * Scope para filtrar versiones activas.
     */
    public function scopeVersionActiva($query)
    {
        return $query->where('estado_version', 'ACTIVO');
    }

    /**
     * Scope para filtrar versiones temporales.
     */
    public function scopeVersionTemporal($query)
    {
        return $query->where('estado_version', 'TEMP');
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
     * Verifica si el elemento es una serie.
     */
    public function isSerie(): bool
    {
        return $this->tipo === 'Serie';
    }

    /**
     * Verifica si el elemento es una subserie.
     */
    public function isSubSerie(): bool
    {
        return $this->tipo === 'SubSerie';
    }

    /**
     * Verifica si el elemento es un tipo de documento.
     */
    public function isTipoDocumento(): bool
    {
        return $this->tipo === 'TipoDocumento';
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
                'nom' => $elemento->nom
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
     * Obtiene estadísticas del elemento y sus hijos.
     */
    public function getEstadisticas(): array
    {
        $estadisticas = [
            'total_hijos' => $this->children()->count(),
            'total_series' => $this->children()->where('tipo', 'Serie')->count(),
            'total_subseries' => $this->children()->where('tipo', 'SubSerie')->count(),
            'total_tipos_documento' => $this->children()->where('tipo', 'TipoDocumento')->count(),
        ];

        return $estadisticas;
    }

    /**
     * Verifica si el elemento puede ser eliminado.
     */
    public function puedeEliminar(): bool
    {
        return !$this->hasChildren();
    }

    /**
     * Obtiene el tipo de padre válido para este elemento.
     */
    public function getTiposPadreValidos(): array
    {
        return match ($this->tipo) {
            'Serie' => [],
            'SubSerie' => ['Serie'],
            'TipoDocumento' => ['Serie', 'SubSerie'],
            default => []
        };
    }

    /**
     * Verifica si un elemento puede ser padre de este.
     */
    public function puedeSerPadre(ClasificacionDocumentalTRD $padre): bool
    {
        $tiposValidos = $this->getTiposPadreValidos();
        return in_array($padre->tipo, $tiposValidos);
    }

    /**
     * Obtiene la información de la Serie asociada a este elemento.
     * 
     * @return array|null Array con información de la Serie o null si no se encuentra
     */
    public function getSerie(): ?array
    {
        // Si es una Serie
        if ($this->isSerie()) {
            return [
                'id' => $this->id,
                'cod' => $this->cod,
                'nom' => $this->nom,
                'tipo' => $this->tipo
            ];
        }

        // Si es una SubSerie, buscar su Serie padre
        if ($this->isSubSerie()) {
            $parent = $this->parent;
            
            // Si parent es un entero (ID), cargar el modelo
            if (is_int($parent)) {
                $parent = self::find($parent);
            }
            
            if ($parent && is_object($parent) && $parent->isSerie()) {
                return [
                    'id' => $parent->id,
                    'cod' => $parent->cod,
                    'nom' => $parent->nom,
                    'tipo' => $parent->tipo
                ];
            }
        }

        // Si es un TipoDocumento, buscar la Serie (abuelo)
        if ($this->isTipoDocumento()) {
            $parent = $this->parent; // SubSerie
            
            // Si parent es un entero (ID), cargar el modelo
            if (is_int($parent)) {
                $parent = self::find($parent);
            }
            
            if ($parent && is_object($parent) && $parent->isSubSerie()) {
                $grandParent = $parent->parent; // Serie
                
                // Si grandParent es un entero (ID), cargar el modelo
                if (is_int($grandParent)) {
                    $grandParent = self::find($grandParent);
                }
                
                if ($grandParent && is_object($grandParent) && $grandParent->isSerie()) {
                    return [
                        'id' => $grandParent->id,
                        'cod' => $grandParent->cod,
                        'nom' => $grandParent->nom,
                        'tipo' => $grandParent->tipo
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Obtiene la información de la SubSerie asociada a este elemento.
     * 
     * @return array|null Array con información de la SubSerie o null si no se encuentra
     */
    public function getSubSerie(): ?array
    {
        // Si es una SubSerie
        if ($this->isSubSerie()) {
            return [
                'id' => $this->id,
                'cod' => $this->cod,
                'nom' => $this->nom,
                'tipo' => $this->tipo
            ];
        }

        // Si es un TipoDocumento, buscar su SubSerie padre
        if ($this->isTipoDocumento()) {
            $parent = $this->parent;
            
            // Si parent es un entero (ID), cargar el modelo
            if (is_int($parent)) {
                $parent = self::find($parent);
            }
            
            if ($parent && is_object($parent) && $parent->isSubSerie()) {
                return [
                    'id' => $parent->id,
                    'cod' => $parent->cod,
                    'nom' => $parent->nom,
                    'tipo' => $parent->tipo
                ];
            }
        }

        return null;
    }
}
