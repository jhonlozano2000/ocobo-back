<?php

namespace App\Models\ControlAcceso;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'browser',
        'device',
        'location',
        'ip_address'
    ];
}
