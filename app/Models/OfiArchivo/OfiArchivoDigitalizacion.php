<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B22: digitalización (M/D) de un expediente en disposición, con la huella de la copia y su
 * certificado. Solo inserción: un trigger impide modificarla o borrarla. El original no se toca.
 */
class OfiArchivoDigitalizacion extends Model
{
    public $timestamps = false;

    protected $table = 'ofi_archivo_digitalizaciones';

    protected $fillable = [
        'expediente_id', 'archivo_path', 'nombre_original', 'formato', 'tamano_bytes', 'hash_sha256',
        'paginas', 'resolucion_dpi', 'observaciones', 'certificado', 'hash_certificado',
        'codigo_verificacion', 'firma', 'user_id', 'digitalizado_at',
    ];

    protected $hidden = ['certificado', 'archivo_path', 'firma'];

    protected $casts = [
        'tamano_bytes' => 'integer',
        'paginas' => 'integer',
        'resolucion_dpi' => 'integer',
        'digitalizado_at' => 'datetime',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
