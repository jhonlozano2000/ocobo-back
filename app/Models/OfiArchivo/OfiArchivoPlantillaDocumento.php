<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class OfiArchivoPlantillaDocumento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ofi_archivo_plantillas_documentos';

    protected $fillable = [
        'nombre_original',
        'nombre_archivo',
        'ruta_completa',
        'peso',
        'extension',
        'mime_type',
        'hash_seguridad',
        'version',
        'descripcion',
        'activo',
        'fecha_vencimiento',
        'categoria_id',
        'user_crea_id',
        'user_actualiza_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'peso' => 'decimal:2',
        'fecha_vencimiento' => 'datetime',
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'user_crea_id');
    }

    public function actualizador()
    {
        return $this->belongsTo(User::class, 'user_actualiza_id');
    }

    public function scopeVigente(Builder $q): Builder
    {
        return $q->where('activo', true)
            ->where(fn($q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>', now()));
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function verificarIntegridad(): bool
    {
        return hash_file('sha256', storage_path("app/plantillas/{$this->ruta_completa}")) === $this->hash_seguridad;
    }

    public function getPesoFormateadoAttribute(): string
    {
        if ($this->peso >= 1024) {
            return number_format($this->peso / 1024, 2) . ' MB';
        }

        return number_format($this->peso, 2) . ' KB';
    }

    public function getEstadoColorAttribute(): string
    {
        if (!$this->activo) {
            return 'error';
        }
        if ($this->fecha_vencimiento && $this->fecha_vencimiento->isPast()) {
            return 'warning';
        }

        return 'success';
    }
}
