<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersSession extends Model
{
    use HasFactory;

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
        'metadata'
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'logout_at'     => 'datetime',
        'is_active'     => 'boolean',
        'metadata'      => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }
}
