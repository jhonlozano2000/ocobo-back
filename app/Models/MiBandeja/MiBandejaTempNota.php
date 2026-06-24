<?php

namespace App\Models\MiBandeja;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiBandejaTempNota extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp_notas';

    public $timestamps = false;

    protected $fillable = [
        'grupo_id',
        'user_id',
        'contenido',
    ];

    protected function contenido(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (string $value) => strip_tags($value),
        );
    }

    protected $casts = [
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
