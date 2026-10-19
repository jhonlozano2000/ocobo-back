<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;

/**
 * B20: nodo del catálogo topográfico (depósito → estante → entrepaño → caja). Los expedientes y
 * sus tomos se ubican en una caja; la carpeta es su número dentro de ella.
 *
 * @property int|null $parent_id
 * @property string $tipo
 * @property string $archivo
 * @property string $codigo
 * @property string $ruta
 * @property int|null $capacidad
 * @property bool $activo
 */
class OfiArchivoUbicacion extends Model
{
    protected $table = 'ofi_archivo_ubicaciones';

    /** Niveles del árbol en orden; cada nodo cuelga del nivel anterior. */
    public const NIVELES = ['deposito', 'estante', 'entrepano', 'caja'];

    public const ETIQUETAS = ['deposito' => 'Depósito', 'estante' => 'Estante', 'entrepano' => 'Entrepaño', 'caja' => 'Caja'];

    protected $fillable = ['parent_id', 'tipo', 'archivo', 'codigo', 'nombre', 'capacidad', 'activo', 'ruta'];

    protected $casts = [
        'parent_id' => 'integer',
        'capacidad' => 'integer',
        'activo' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Nivel que cuelga de este nodo; null en la caja. */
    public function nivelHijo(): ?string
    {
        return self::NIVELES[array_search($this->tipo, self::NIVELES, true) + 1] ?? null;
    }
}
