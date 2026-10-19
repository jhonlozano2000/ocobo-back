<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Consulta o descarga de un documento durante un préstamo electrónico (§3.10). Solo se inserta.
 *
 * @property int $prestamo_id
 * @property int $documento_id
 * @property int $user_id
 * @property string $accion ver|descargar
 */
class OfiArchivoPrestamoAcceso extends Model
{
    public const UPDATED_AT = null;

    public const ACCIONES = ['ver', 'descargar'];

    protected $table = 'ofi_archivo_prestamo_accesos';

    protected $fillable = ['prestamo_id', 'documento_id', 'user_id', 'accion', 'ip', 'user_agent'];

    public function prestamo()
    {
        return $this->belongsTo(OfiArchivoPrestamo::class, 'prestamo_id');
    }

    public function documento()
    {
        return $this->belongsTo(OfiArchivoExpedienteDocumento::class, 'documento_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
