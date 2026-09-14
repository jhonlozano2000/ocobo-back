<?php

namespace App\Models\MiDisco;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class MiDiscoArchivo extends Model
{
    protected $table = 'mi_disco_archivos';

    protected $fillable = [
        'nombre',
        'nombre_original',
        'carpeta_id',
        'user_id',
        'ruta_almacenamiento',
        'peso',
        'mime_type',
        'hash_sha256',
    ];

    protected $casts = [
        'peso' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(MiDiscoCarpeta::class, 'carpeta_id');
    }

    public function getTamañoFormateadoAttribute(): string
    {
        $bytes = $this->peso;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
