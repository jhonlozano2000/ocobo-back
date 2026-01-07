<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\configVentanilla;
use App\Models\Configuracion\ConfigSede;
use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\ControlAcceso\UserCargo;
use App\Models\ControlAcceso\UsersSession;
use App\Models\VentanillaUnica\VentanillaUnica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Helpers\ArchivoHelper;
use App\Models\VentanillaUnica\VentanillaRadicaReci;

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
        return ArchivoHelper::obtenerUrl($this->avatar, 'avatars');
    }

    public function getFirmaUrlAttribute()
    {
        return ArchivoHelper::obtenerUrl($this->firma, 'firmas');
    }

    /**
     * Obtiene la URL de cualquier archivo del usuario usando ArchivoHelper.
     * @param string $campo Nombre del atributo (ej: 'avatar', 'firma')
     * @param string $disk Nombre del disco
     * @return string|null
     */
    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }

    /**
     * Relación con los cargos históricos del usuario a través del modelo UserCargo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cargosHistoricos()
    {
        return $this->hasMany(UserCargo::class)
            ->with('cargo')
            ->orderBy('fecha_inicio', 'desc');
    }

    /**
     * Relación para obtener el cargo ACTIVO del usuario.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cargoActivo()
    {
        return $this->hasOne(UserCargo::class)
            ->with('cargo')
            ->where('estado', true)
            ->whereNull('fecha_fin');
    }

    /**
     * Relación many-to-many con CalidadOrganigrama (para compatibilidad).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function cargos()
    {
        return $this->belongsToMany(CalidadOrganigrama::class, 'users_cargos', 'user_id', 'cargo_id')
            ->withPivot('fecha_inicio', 'fecha_fin', 'observaciones', 'estado')
            ->withTimestamps();
    }

    /**
     * Asigna un nuevo cargo al usuario y finaliza el cargo anterior si existe.
     *
     * @param int $cargoId ID del cargo a asignar
     * @param string|null $fechaInicio Fecha de inicio (por defecto hoy)
     * @param string|null $observaciones Observaciones adicionales
     * @return UserCargo
     * @throws \Exception
     */
    public function asignarCargo(int $cargoId, ?string $fechaInicio = null, ?string $observaciones = null): UserCargo
    {
        // Validar que el cargo existe y es de tipo 'Cargo'
        $cargo = CalidadOrganigrama::where('id', $cargoId)
            ->where('tipo', 'Cargo')
            ->first();

        if (!$cargo) {
            throw new \Exception('El cargo especificado no existe o no es válido.');
        }

        // Finalizar cargo activo anterior
        $this->finalizarCargoActivo();

        // Crear nueva asignación de cargo
        return UserCargo::create([
            'user_id' => $this->id,
            'cargo_id' => $cargoId,
            'fecha_inicio' => $fechaInicio ?? now()->format('Y-m-d'),
            'observaciones' => $observaciones,
            'estado' => true
        ]);
    }

    /**
     * Finaliza el cargo activo actual del usuario.
     *
     * @param string|null $fechaFin Fecha de finalización (por defecto hoy)
     * @param string|null $observaciones Observaciones adicionales
     * @return bool
     */
    public function finalizarCargoActivo(?string $fechaFin = null, ?string $observaciones = null): bool
    {
        $cargoActivo = $this->cargoActivo;

        if ($cargoActivo) {
            return $cargoActivo->finalizar($fechaFin, $observaciones);
        }

        return false;
    }

    /**
     * Obtiene el cargo activo del usuario como objeto.
     *
     * @return UserCargo|null
     */
    public function obtenerCargoActivo(): ?UserCargo
    {
        return UserCargo::cargoActivoDelUsuario($this->id);
    }

    /**
     * Verifica si el usuario tiene un cargo activo.
     *
     * @return bool
     */
    public function tieneCargoActivo(): bool
    {
        return $this->cargoActivo !== null;
    }

    /**
     * Obtiene información detallada del cargo actual.
     *
     * @return array|null
     */
    public function getInfoCargoActivo(): ?array
    {
        $cargoActivo = $this->obtenerCargoActivo();
        return $cargoActivo ? $cargoActivo->getDetalleCompleto() : null;
    }

    /**
     * Obtiene el historial completo de cargos del usuario.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getHistorialCargos()
    {
        return $this->cargosHistoricos;
    }

    public function ventanillas()
    {
        return $this->belongsToMany(configVentanilla::class, 'users_ventanillas');
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

    /**
     * Obtiene la división política asociada al usuario.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function divisionPolitica()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'divi_poli_id');
    }

    /**
     * Obtiene las sedes asociadas al usuario a través de la tabla pivot.
     */
    public function sedes()
    {
        return $this->belongsToMany(ConfigSede::class, 'users_sedes', 'user_id', 'sede_id')
            ->withPivot('estado', 'observaciones')
            ->withTimestamps();
    }

    /**
     * Obtiene solo las sedes activas del usuario.
     */
    public function sedesActivas()
    {
        return $this->belongsToMany(ConfigSede::class, 'users_sedes', 'user_id', 'sede_id')
            ->withPivot('estado', 'observaciones')
            ->wherePivot('estado', true)
            ->withTimestamps();
    }

    /**
     * Asigna una sede al usuario.
     */
    public function asignarSede($sedeId, $observaciones = null)
    {
        return $this->sedes()->attach($sedeId, [
            'estado' => true,
            'observaciones' => $observaciones
        ]);
    }

    /**
     * Desasigna una sede del usuario.
     */
    public function desasignarSede($sedeId)
    {
        return $this->sedes()->detach($sedeId);
    }

    /**
     * Activa la relación con una sede específica.
     */
    public function activarSede($sedeId)
    {
        return $this->sedes()->updateExistingPivot($sedeId, ['estado' => true]);
    }

    /**
     * Desactiva la relación con una sede específica.
     */
    public function desactivarSede($sedeId)
    {
        return $this->sedes()->updateExistingPivot($sedeId, ['estado' => false]);
    }

    public function usuarioCreaRadicado()
    {
        return $this->hasMany(VentanillaRadicaReci::class, 'usuario_crea');
    }
}
