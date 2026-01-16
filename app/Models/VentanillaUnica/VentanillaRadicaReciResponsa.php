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
     * Obtiene el UserCargo asociado al responsable.
     */
    public function userCargo()
    {
        return $this->belongsTo(\App\Models\ControlAcceso\UserCargo::class, 'users_cargos_id');
    }

    /**
     * Obtiene el usuario responsable (relación legacy para compatibilidad).
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

    /**
     * Obtiene información formateada del responsable para respuestas API.
     * Optimizado para usar relaciones ya cargadas con eager loading.
     *
     * @return array|null
     */
    public function getInfoResponsable(): ?array
    {
        // Usar relación ya cargada si existe (optimización)
        $userCargo = $this->relationLoaded('userCargo') ? $this->userCargo : $this->userCargo()->with(['user', 'cargo'])->first();

        if (!$userCargo) {
            return null;
        }

        // Usar relaciones ya cargadas para evitar consultas N+1 (optimizado)
        $user = $userCargo->relationLoaded('user') ? $userCargo->user : null;
        $cargo = $userCargo->relationLoaded('cargo') ? $userCargo->cargo : null;

        return [
            'id' => $this->id,
            'custodio' => $this->custodio,
            'fechor_visto' => $this->fechor_visto,
            'fecha_asignacion' => $this->created_at,
            'usuario' => $user ? $user->getInfoUsuario() : null,
            'cargo' => $cargo ? [
                'id' => $cargo->id,
                'nombre' => $cargo->nom_organico,
                'codigo' => $cargo->cod_organico,
                'tipo' => $cargo->tipo,
            ] : null,
        ];
    }
}
