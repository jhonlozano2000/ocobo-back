<?php

namespace App\Models\OfiArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * B17: lote de transferencia documental de una dependencia (diseño de expedientes §3.7).
 *
 * Se crea enviado, con su FUID firmado por quien envía. Quien recibe coteja cada expediente:
 * si todos son conformes lo recibe y se genera el acta de entrega; si alguno no lo es, lo devuelve
 * con observaciones y los expedientes vuelven al archivo de origen.
 *
 * @property int $id
 * @property string $codigo
 * @property string $tipo primaria|secundaria
 * @property string $estado enviado|recibido|devuelto
 * @property string $fuid XLSX (binario)
 * @property string|null $acta PDF (binario)
 */
class OfiArchivoTransferenciaLote extends Model
{
    protected $table = 'ofi_archivo_transferencia_lotes';

    public const ESTADOS = ['enviado', 'recibido', 'devuelto'];

    protected $fillable = [
        'codigo',
        'tipo',
        'dependencia_id',
        'dependencia_cod',
        'dependencia_nom',
        'origen',
        'destino',
        'estado',
        'observaciones',
        'observaciones_recepcion',
        'usuario_envia_id',
        'firmante_envio',
        'fecha_envio',
        'firma_envio',
        'usuario_recibe_id',
        'firmante_recepcion',
        'fecha_recepcion',
        'firma_recepcion',
        'fuid',
        'hash_fuid',
        'acta',
        'hash_acta',
        'codigo_verificacion',
    ];

    protected $hidden = ['fuid', 'acta', 'firma_envio', 'firma_recepcion'];

    protected $casts = [
        'dependencia_id' => 'integer',
        'usuario_envia_id' => 'integer',
        'usuario_recibe_id' => 'integer',
        'fecha_envio' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OfiArchivoTransferencia::class, 'lote_id')->orderBy('id');
    }

    public function dependencia()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_id');
    }

    public function usuarioEnvia()
    {
        return $this->belongsTo(User::class, 'usuario_envia_id');
    }

    public function usuarioRecibe()
    {
        return $this->belongsTo(User::class, 'usuario_recibe_id');
    }
}
