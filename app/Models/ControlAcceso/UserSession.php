<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'last_login_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
