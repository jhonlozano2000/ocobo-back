<?php

namespace App\Models\MiDisco;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class MiDiscoCarpeta extends Model
{
    protected $table = 'mi_disco_carpetas';

    protected $fillable = [
        'nombre',
        'carpeta_padre_id',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carpeta_padre_id');
    }

    public function subcarpetas(): HasMany
    {
        return $this->hasMany(self::class, 'carpeta_padre_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(MiDiscoArchivo::class, 'carpeta_id');
    }
}
