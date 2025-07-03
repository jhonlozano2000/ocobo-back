<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersSession extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'last_login_at'];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
