<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosArchivoEliminado extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_archivo_eliminados';

    public $timestamps = false;

    protected $fillable = [
        'radica_enviado_id',
        'archivo',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radica_enviado_id');
    }
}
