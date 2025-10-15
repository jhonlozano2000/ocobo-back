<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciResponsa extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_responsa';

    protected $fillable = [
        'radica_reci_id',
        'users_cargos_id',
        'custodio',
        'fechor_visto'
    ];

    protected $casts = [
        'custodio' => 'boolean',
        'fechor_visto' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene el usuario responsable.
     */
    public function usuarioCargo()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Obtiene la radicación asociada.
     */
    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }

    /**
     * Obtiene el usuario que eliminó el archivo.
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }

    /**
     * Scope para filtrar por custodios.
     */
    public function scopeCustodios($query)
    {
        return $query->where('custodio', true);
    }

    /**
     * Scope para filtrar por no custodios.
     */
    public function scopeNoCustodios($query)
    {
        return $query->where('custodio', false);
    }

    /**
     * Verifica si el responsable es custodio.
     */
    public function isCustodio()
    {
        return $this->custodio;
    }

    /**
     * Marca al responsable como custodio.
     */
    public function marcarComoCustodio()
    {
        $this->update(['custodio' => true]);
    }

    /**
     * Desmarca al responsable como custodio.
     */
    public function desmarcarComoCustodio()
    {
        $this->update(['custodio' => false]);
    }
}
