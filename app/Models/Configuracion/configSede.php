<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigSede extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'email',
        'ubicacion',
        'divi_poli_id',
        'estado'
    ];

    /**
     * Obtiene la división política asociada a esta sede.
     */
    public function divisionPolitica()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'divi_poli_id');
    }

    /**
     * Obtiene las ventanillas asociadas a esta sede.
     */
    public function ventanillas()
    {
        return $this->hasMany(ConfigVentanilla::class);
    }

    /**
     * Obtiene los usuarios asociados a esta sede a través de la tabla pivot.
     */
    public function usuarios()
    {
        return $this->belongsToMany(\App\Models\User::class, 'users_sedes', 'sede_id', 'user_id')
            ->withPivot('estado', 'observaciones')
            ->withTimestamps();
    }

    /**
     * Obtiene solo los usuarios activos de esta sede.
     */
    public function usuariosActivos()
    {
        return $this->belongsToMany(\App\Models\User::class, 'users_sedes', 'sede_id', 'user_id')
            ->withPivot('estado', 'observaciones')
            ->wherePivot('estado', true)
            ->withTimestamps();
    }

    /**
     * Asigna un usuario a esta sede.
     */
    public function asignarUsuario($userId, $observaciones = null)
    {
        return $this->usuarios()->attach($userId, [
            'estado' => true,
            'observaciones' => $observaciones
        ]);
    }

    /**
     * Desasigna un usuario de esta sede.
     */
    public function desasignarUsuario($userId)
    {
        return $this->usuarios()->detach($userId);
    }

    /**
     * Activa la relación con un usuario específico.
     */
    public function activarUsuario($userId)
    {
        return $this->usuarios()->updateExistingPivot($userId, ['estado' => true]);
    }

    /**
     * Desactiva la relación con un usuario específico.
     */
    public function desactivarUsuario($userId)
    {
        return $this->usuarios()->updateExistingPivot($userId, ['estado' => false]);
    }
}
