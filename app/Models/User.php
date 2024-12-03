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
        'estado'
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

    // Relación con Organigrama a través de la tabla pivote
    public function organigramas(): BelongsToMany
    {
        return $this->belongsToMany(CalidadOrganigrama::class, 'organigrama_user')
            ->withPivot('start_date', 'end_date')
            ->withTimestamps();
    }

    // Método para asignar un nuevo cargo a un usuario
    public function assignOrganigrama(CalidadOrganigrama $organigrama)
    {
        $this->organigramas()->attach($organigrama->id, [
            'start_date' => Carbon::now(),
            'end_date' => null // Esto indica que el cargo está activo
        ]);
    }

    // Método para obtener el cargo actual del usuario (si lo tiene)
    public function currentOrganigrama()
    {
        return $this->organigramas()
            ->wherePivot('end_date', null)
            ->first();
    }

    // Método para terminar el cargo actual del usuario (definir end_date)
    public function endCurrentOrganigrama()
    {
        $currentOrganigrama = $this->currentOrganigrama();
        if ($currentOrganigrama) {
            $this->organigramas()->updateExistingPivot($currentOrganigrama->id, [
                'end_date' => Carbon::now()
            ]);
        }
    }

    // Método para obtener el historial completo de cargos del usuario
    public function organigramaHistory()
    {
        return $this->organigramas()->withPivot('start_date', 'end_date')->get();
    }
}
