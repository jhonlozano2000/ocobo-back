<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersActivityLog extends Model
{
    protected $table = 'users_activity_logs';

    protected $fillable = [
        'user_id',
        'users_session_id',
        'module',
        'action',
        'description',
        'entity_id',
        'entity_type',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'is_critical'
    ];

    protected $casts = [
        'old_values'   => 'array',
        'new_values'   => 'array',
        'is_critical'  => 'boolean',
        'created_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entity()
    {
        return $this->morphTo();
    }

    public function session()
    {
        return $this->belongsTo(\App\Models\ControlAcceso\UsersSession::class, 'users_session_id');
    }

    public static function log(array $data): self
    {
        $user = auth()->user();
        $request = request();

        return self::create([
            'user_id'         => $user?->id,
            'users_session_id'=> self::getActiveSessionId(),
            'module'          => $data['module'] ?? 'Unknown',
            'action'          => $data['action'] ?? 'unknown',
            'description'     => $data['description'] ?? null,
            'entity_id'       => $data['entity_id'] ?? null,
            'entity_type'     => $data['entity_type'] ?? null,
            'old_values'      => $data['old_values'] ?? null,
            'new_values'      => $data['new_values'] ?? null,
            'ip_address'      => $request?->ip(),
            'user_agent'      => $request?->userAgent(),
            'is_critical'     => $data['is_critical'] ?? false,
        ]);
    }

    private static function getActiveSessionId(): ?int
    {
        return \App\Models\ControlAcceso\UsersSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('last_login_at', 'desc')
            ->value('id');
    }

    public static function actions(string $module): array
    {
        return [
            'create'   => 'Crear',
            'update'   => 'Actualizar',
            'delete'   => 'Eliminar',
            'view'     => 'Ver',
            'export'   => 'Exportar',
            'download' => 'Descargar',
            'assign'   => 'Asignar',
            'change_status' => 'Cambiar estado',
            'login'    => 'Iniciar sesión',
            'logout'   => 'Cerrar sesión',
        ];
    }
}