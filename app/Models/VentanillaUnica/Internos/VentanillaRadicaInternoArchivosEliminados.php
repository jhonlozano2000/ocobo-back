<?php

namespace App\Models\VentanillaUnica\Internos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoArchivosEliminados extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_interno_archivos_eliminados';

    protected $fillable = [
        'radica_interno_id',
        'archivo',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'eliminado_por');
    }

    public function radicaInterno()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }
}
