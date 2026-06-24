<?php

namespace App\Models\MiBandeja;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiBandejaTempAuditLog extends Model
{
    protected $table = 'grupo_colaborativo_audits';

    public const UPDATED_AT = null;

    protected $fillable = [
        'grupo_id',
        'user_id',
        'accion',
        'ip_origen',
        'user_agent',
        'payload_old',
        'payload_new',
    ];

    protected $casts = [
        'payload_old' => 'json',
        'payload_new' => 'json',
        'created_at' => 'datetime',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(MiBandejaTemp::class, 'grupo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
