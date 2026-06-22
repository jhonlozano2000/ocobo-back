<?php

namespace App\Models\VentanillaUnica\Comunes;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentanillaPqrsArchivo extends Model
{
    use SoftDeletes;

    protected $table = 'ventanilla_pqrs_archivos';

    protected $fillable = [
        'ventanilla_pqrs_id',
        'tipo',
        'nombre_original',
        'nombre_guardado',
        'path',
        'mime_type',
        'tamanio',
        'hash_sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'tamanio' => 'integer',
    ];

    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'ventanilla_pqrs_id');
    }

    public function usuarioSubio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeDigitales($query)
    {
        return $query->where('tipo', 'digital');
    }

    public function scopeAdjuntos($query)
    {
        return $query->where('tipo', 'adjunto');
    }

    public function getTamanioFormateadoAttribute(): string
    {
        $bytes = $this->tamanio;
        if ($bytes === 0) {
            return '0 Bytes';
        }

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2).' '.$sizes[$i];
    }
}
