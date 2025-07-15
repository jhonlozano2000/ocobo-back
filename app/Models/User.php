<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\configVentanilla;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\ControlAcceso\UsersSession;
use App\Models\VentanillaUnica\VentanillaUnica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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

    public function getAvatarUrlAttribute()
    {
        return $this->avatar
            ? Storage::disk('avatars')->url($this->avatar)
            : null;
    }

    public function getFirmaUrlAttribute()
    {
        return $this->firma
            ? Storage::disk('firmas')->url($this->firma)
            : null;
    }

    // Relación con los cargos históricos del usuario
    public function cargos()
    {
        return $this->belongsToMany(CalidadOrganigrama::class, 'users_cargos', 'user_id', 'organigrama_id')
            ->withPivot('start_date', 'end_date')
            ->withTimestamps();
    }

    // Relación para obtener el cargo ACTIVO del usuario
    public function cargoActivo()
    {
        return $this->belongsToMany(CalidadOrganigrama::class, 'users_cargos', 'user_id', 'organigrama_id')
            ->withPivot('start_date', 'end_date')
            ->whereNull('users_cargos.end_date') // Solo los activos
            ->withTimestamps();
    }

    // Método para asignar un nuevo cargo y desactivar el anterior
    public function assignCargo($cargoId)
    {
        // Finaliza cualquier cargo activo antes de asignar uno nuevo
        $this->cargos()->updateExistingPivot($cargoId, ['end_date' => now()]);

        // Asigna el nuevo cargo con la fecha de inicio
        return $this->cargos()->attach($cargoId, ['start_date' => now()]);
    }

    // Método para finalizar el cargo activo actual
    public function endCurrentCargo()
    {
        // Obtiene el cargo activo del usuario
        $currentCargo = $this->cargos()->whereNull('users_cargos.end_date')->first();

        // Si existe un cargo activo, lo finaliza
        if ($currentCargo) {
            $this->cargos()->updateExistingPivot($currentCargo->id, ['end_date' => now()]);
        }
    }

    public function ventanillas()
    {
        return $this->belongsToMany(configVentanilla::class, 'user_ventanillas');
    }

    public function ventanillasPermitidas()
    {
        return $this->belongsToMany(VentanillaUnica::class, 'ventanilla_permisos', 'user_id', 'ventanilla_id');
    }

    public function sessions()
    {
        return $this->hasMany(UsersSession::class)->latest('last_login_at');
    }

    public function notificationSettings()
    {
        return $this->hasOne(UserNotificationSetting::class);
    }
}
