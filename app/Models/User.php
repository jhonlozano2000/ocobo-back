<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'divi_poli_id',
        'num_docu',
        'nombres',
        'apellidos',
        'tel',
        'movil',
        'dir',
        'email',
        'firma',
        'avatar',
        'password',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relación con la tabla pivote users_cargos
    public function cargos()
    {
        return $this->belongsToMany(CalidadOrganigrama::class, 'users_cargos', 'user_id', 'organigrama_id')
            ->withPivot('start_date', 'end_date')  // Fechas de inicio y fin del cargo
            ->withTimestamps();  // Tiempos de creación y actualización
    }

    public function cargoActivo()
    {
        return $this->belongsToMany(CalidadOrganigrama::class, 'users_cargos', 'user_id', 'organigrama_id')
            ->withPivot('start_date', 'end_date')  // Fechas de inicio y fin del cargo
            ->whereNull('end_date')
            ->withTimestamps();  // Tiempos de creación y actualización
    }

    // Método para asignar un cargo a un usuario
    public function assignCargo($organigramaId)
    {
        // Finaliza cualquier cargo activo antes de asignar uno nuevo
        $this->cargos()->updateExistingPivot($organigramaId, ['end_date' => now()]);

        // Asigna el nuevo cargo con la fecha de inicio
        return $this->cargos()->attach($organigramaId, ['start_date' => now()]);
    }

    // Método para eliminar un cargo de un usuario
    public function removeCargo($organigramaId)
    {
        // Finaliza el cargo especificado
        $this->cargos()->updateExistingPivot($organigramaId, ['end_date' => now()]);
    }

    // Método para finalizar el cargo activo actual
    public function endCurrentCargo()
    {
        // Obtiene el cargo activo (sin fecha de fin)
        $currentCargo = $this->cargos()->whereNull('end_date')->first();

        // Si existe un cargo activo, lo finaliza
        if ($currentCargo) {
            $this->removeCargo($currentCargo->pivot->organigrama_id);
        }
    }
}
