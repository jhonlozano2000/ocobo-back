<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsersAuthenticationLog extends Model
{
    protected $table = 'users_authentication_logs';

    protected $fillable = [
        'user_id',
        'event',
        'ip_address',
        'user_agent',
        'email',
        'success',
        'details',
        'country',
        'city'
    ];

    protected $casts = [
        'success'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function logEvent(array $data): self
    {
        $request = request();

        return self::create([
            'user_id'    => $data['user_id'] ?? null,
            'event'      => $data['event'],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'email'      => $data['email'] ?? null,
            'success'    => $data['success'] ?? false,
            'details'    => $data['details'] ?? null,
            'country'    => $data['country'] ?? null,
            'city'       => $data['city'] ?? null,
        ]);
    }

    public static function events(): array
    {
        return [
            'login_success'       => 'Login exitoso',
            'login_failed'        => 'Login fallido',
            'logout'              => 'Cerrar sesión',
            'password_changed'    => 'Contraseña cambiada',
            'password_reset'     => 'Restablecimiento de contraseña',
            'session_expired'     => 'Sesión expirada',
            'session_invalid'     => 'Sesión inválida',
            'suspicious_activity'=> 'Actividad sospechosa',
            'account_locked'      => 'Cuenta bloqueada',
            'account_unlocked'   => 'Cuenta desbloqueada',
            '2fa_enabled'        => '2FA habilitado',
            '2fa_disabled'       => '2FA deshabilitado',
            'mfa_code_sent'      => 'Código MFA enviado',
            'mfa_code_verified'  => 'Código MFA verificado',
            'mfa_code_failed'    => 'Código MFA fallido',
        ];
    }
}