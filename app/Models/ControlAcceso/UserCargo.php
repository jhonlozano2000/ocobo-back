<?php

namespace App\Models\ControlAcceso;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCargo extends Model
{
    use HasFactory;

    protected $table = 'users_cargos';

    protected $fillable = [
        'user_id',
        'cargo_id',
        'fecha_inicio',
        'fecha_fin',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'estado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el usuario.
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el cargo del organigrama.
     *
     * @return BelongsTo
     */
    public function cargo()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'cargo_id');
    }

    /**
     * Scope para obtener solo cargos activos.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true)->whereNull('fecha_fin');
    }

    /**
     * Scope para obtener cargos finalizados.
     */
    public function scopeFinalizados(Builder $query): Builder
    {
        return $query->where('estado', false)->orWhereNotNull('fecha_fin');
    }

    /**
     * Scope para obtener cargos vigentes en una fecha específica.
     *
     * @param  string|Carbon  $fecha
     */
    public function scopeVigentesEn(Builder $query, $fecha): Builder
    {
        $fecha = Carbon::parse($fecha)->format('Y-m-d');

        return $query->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $fecha);
            });
    }

    /**
     * Scope para filtrar por usuario.
     */
    public function scopeDelUsuario(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por cargo.
     */
    public function scopeDelCargo(Builder $query, int $cargoId): Builder
    {
        return $query->where('cargo_id', $cargoId);
    }

    /**
     * Verifica si el cargo está activo actualmente.
     */
    public function estaActivo(): bool
    {
        return $this->estado && is_null($this->fecha_fin);
    }

    /**
     * Verifica si el cargo está vigente en una fecha específica.
     *
     * @param  string|Carbon  $fecha
     */
    public function estaVigenteEn($fecha): bool
    {
        $fecha = Carbon::parse($fecha);
        $inicio = Carbon::parse($this->fecha_inicio);
        $fin = $this->fecha_fin ? Carbon::parse($this->fecha_fin) : null;

        return $inicio->lte($fecha) && ($fin === null || $fin->gte($fecha));
    }

    /**
     * Finaliza el cargo actual.
     *
     * @param  string|Carbon|null  $fechaFin
     * @param  string|null  $observaciones
     */
    public function finalizar($fechaFin = null, $observaciones = null): bool
    {
        $this->fecha_fin = $fechaFin ?? now()->format('Y-m-d');
        $this->estado = false;

        if ($observaciones) {
            $this->observaciones = $this->observaciones ?
                $this->observaciones.' | '.$observaciones :
                $observaciones;
        }

        return $this->save();
    }

    /**
     * Reactiva el cargo (solo si no ha sido finalizado definitivamente).
     */
    public function reactivar(): bool
    {
        if ($this->fecha_fin && Carbon::parse($this->fecha_fin)->isPast()) {
            return false; // No se puede reactivar un cargo finalizado en el pasado
        }

        $this->fecha_fin = null;
        $this->estado = true;

        return $this->save();
    }

    /**
     * Obtiene la duración del cargo en días.
     */
    public function getDuracionEnDias(): ?int
    {
        if (! $this->fecha_inicio) {
            return null;
        }

        $inicio = Carbon::parse($this->fecha_inicio);
        $fin = $this->fecha_fin ? Carbon::parse($this->fecha_fin) : now();

        return $inicio->diffInDays($fin);
    }

    /**
     * Obtiene información detallada del cargo.
     */
    public function getDetalleCompleto(): array
    {
        return [
            'id' => $this->id,
            'usuario' => [
                'id' => $this->user->id,
                'nombres' => $this->user->nombres,
                'apellidos' => $this->user->apellidos,
                'email' => $this->user->email,
            ],
            'cargo' => [
                'id' => $this->cargo->id,
                'nombre' => $this->cargo->nom_organico,
                'codigo' => $this->cargo->cod_organico,
                'tipo' => $this->cargo->tipo,
                'jerarquia' => $this->cargo->getJerarquiaCompleta(),
            ],
            'periodo' => [
                'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
                'duracion_dias' => $this->getDuracionEnDias(),
                'esta_activo' => $this->estaActivo(),
            ],
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Obtiene el cargo activo de un usuario específico.
     */
    public static function cargoActivoDelUsuario(int $userId): ?UserCargo
    {
        return self::delUsuario($userId)
            ->activos()
            ->with(['cargo', 'user'])
            ->first();
    }

    /**
     * Obtiene todos los usuarios asignados a un cargo específico.
     *
     * @return Collection
     */
    public static function usuariosDelCargo(int $cargoId, bool $soloActivos = true)
    {
        $query = self::delCargo($cargoId)->with(['user', 'cargo']);

        if ($soloActivos) {
            $query->activos();
        }

        return $query->get();
    }

    /**
     * Scope para obtener cargos de usuarios activos.
     */
    public function scopeConUsuariosActivos(Builder $query): Builder
    {
        return $query->whereHas('user', function ($q) {
            $q->where('estado', 1);
        });
    }

    /**
     * Scope para filtrar por tipo de cargo.
     */
    public function scopeDelTipo(Builder $query, string $tipo): Builder
    {
        return $query->whereHas('cargo', function ($q) use ($tipo) {
            $q->where('tipo', $tipo);
        });
    }

    /**
     * Scope para obtener cargos vigentes en un rango de fechas.
     *
     * @param  string|Carbon  $fechaInicio
     * @param  string|Carbon|null  $fechaFin
     */
    public function scopeVigentesEnRango(Builder $query, $fechaInicio, $fechaFin = null): Builder
    {
        $fechaInicio = Carbon::parse($fechaInicio)->format('Y-m-d');
        $fechaFin = $fechaFin ? Carbon::parse($fechaFin)->format('Y-m-d') : null;

        return $query->where(function ($q) use ($fechaInicio, $fechaFin) {
            // Cargos que inician antes o en la fecha de fin
            $q->where('fecha_inicio', '<=', $fechaFin ?? now()->format('Y-m-d'));

            // Y que terminan después de la fecha de inicio (o no han terminado)
            $q->where(function ($subQ) use ($fechaInicio) {
                $subQ->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $fechaInicio);
            });
        });
    }

    /**
     * Obtiene estadísticas de asignaciones de cargos.
     */
    public static function obtenerEstadisticas(): array
    {
        $totalAsignaciones = self::count();
        $asignacionesActivas = self::activos()->count();
        $asignacionesFinalizadas = self::finalizados()->count();
        $usuariosConCargo = self::activos()->distinct('user_id')->count();
        $cargosConUsuarios = self::activos()->distinct('cargo_id')->count();

        return [
            'total_asignaciones' => $totalAsignaciones,
            'asignaciones_activas' => $asignacionesActivas,
            'asignaciones_finalizadas' => $asignacionesFinalizadas,
            'usuarios_con_cargo' => $usuariosConCargo,
            'cargos_con_usuarios' => $cargosConUsuarios,
            'porcentaje_activas' => $totalAsignaciones > 0 ? round(($asignacionesActivas / $totalAsignaciones) * 100, 2) : 0,
        ];
    }
}
