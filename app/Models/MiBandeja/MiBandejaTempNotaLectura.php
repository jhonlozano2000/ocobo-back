<?php

namespace App\Models\MiBandeja;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiBandejaTempNotaLectura extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp_notas_lecturas';

    protected $fillable = [
        'grupo_id',
        'user_id',
        'ultima_lectura_at',
    ];

    protected $casts = [
        'ultima_lectura_at' => 'datetime',
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
