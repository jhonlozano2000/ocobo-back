<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciArchivoEliminado extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_archivos_eliminados';

    protected $fillable = ['radicado_id', 'archivo', 'deleted_by', 'deleted_at'];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }
}
