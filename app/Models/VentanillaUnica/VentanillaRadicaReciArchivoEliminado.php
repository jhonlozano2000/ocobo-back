<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciArchivoEliminado extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_archivo_eliminados';

    protected $fillable = [
        'radicado_id',
        'archivo',
        'deleted_by',
        'deleted_at'
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene el usuario que eliminó el archivo.
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }

    /**
     * Obtiene la radicación asociada.
     */
    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    /**
     * Scope para filtrar por fecha de eliminación.
     */
    public function scopeEliminadosEn($query, $fecha)
    {
        return $query->whereDate('deleted_at', $fecha);
    }

    /**
     * Scope para filtrar por usuario que eliminó.
     */
    public function scopeEliminadoPor($query, $userId)
    {
        return $query->where('deleted_by', $userId);
    }

    /**
     * Obtiene el nombre del archivo sin la ruta.
     */
    public function getNombreArchivoAttribute()
    {
        return basename($this->archivo);
    }

    /**
     * Obtiene la extensión del archivo.
     */
    public function getExtensionAttribute()
    {
        return pathinfo($this->archivo, PATHINFO_EXTENSION);
    }
}
