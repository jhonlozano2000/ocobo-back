<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciArchivo extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_archivos';

    protected $fillable = [
        'radicado_id',
        'archivo',
    ];
}
