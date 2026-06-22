<?php

namespace App\Models\Transversal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmaEvento extends Model
{
    use HasFactory;

    protected $table = 'firmas_eventos';

    protected $fillable = [
        'documentable_id',
        'documentable_type',
        'user_id',
        'hash_original',
        'hash_firmado',
        'otp_utilizado',
        'ip_address',
        'user_agent',
        'fecha_firma',
    ];

    protected $casts = [
        'fecha_firma' => 'datetime',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
