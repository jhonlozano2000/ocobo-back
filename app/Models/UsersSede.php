<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Configuracion\ConfigSede;

class UsersSede extends Model
{
    use HasFactory;

    protected $table = 'users_sedes';

    protected $fillable = [
        'user_id',
        'sede_id',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'estado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene el usuario asociado a esta relación.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene la sede asociada a esta relación.
     */
    public function sede()
    {
        return $this->belongsTo(ConfigSede::class, 'sede_id');
    }

    /**
     * Scope para filtrar por estado activo.
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', true);
    }

    /**
     * Scope para filtrar por estado inactivo.
     */
    public function scopeInactivo($query)
    {
        return $query->where('estado', false);
    }

    /**
     * Verifica si la relación está activa.
     */
    public function isActivo()
    {
        return $this->estado;
    }

    /**
     * Activa la relación usuario-sede.
     */
    public function activar()
    {
        $this->update(['estado' => true]);
    }

    /**
     * Desactiva la relación usuario-sede.
     */
    public function desactivar()
    {
        $this->update(['estado' => false]);
    }
}
