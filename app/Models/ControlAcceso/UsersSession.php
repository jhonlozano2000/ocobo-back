<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersSession extends Model
{
    use HasFactory;

    /**
     * Clave de la sesión HTTP que guarda el id de esta fila; con ella el middleware
     * Authenticate invalida la cookie cuando la fila se cierra (SES-001).
     */
    public const CLAVE_SESION = 'users_session_id';

    protected $fillable = [
        'user_id',
        'ip_address',
        'ip_address_extra',
        'user_agent',
        'device_type',
        'browser',
        'operating_system',
        'country',
        'city',
        'referer_url',
        'last_login_at',
        'logout_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'logout_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Marca como cerrada la fila de users_sessions indicada, si sigue activa.
     */
    public static function cerrarActual(int|string|null $sesionId): void
    {
        if ($sesionId) {
            self::whereKey($sesionId)->where('is_active', true)->update(['is_active' => false, 'logout_at' => now()]);
        }
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }
}
