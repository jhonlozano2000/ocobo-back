<?php

namespace App\Models\VentanillaUnica;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\configSede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaUnica extends Model
{
    use HasFactory;

    protected $table = 'ventanillas_unicas';

    protected $fillable = [
        'sede_id',
        'nombre',
        'descripcion'
    ];

    // Relación con la sede
    public function sede()
    {
        return $this->belongsTo(configSede::class, 'sede_id');
    }

    // Relación con los usuarios que pueden radicar en la ventanilla
    public function usuariosPermitidos()
    {
        return $this->belongsToMany(User::class, 'ventanilla_permisos', 'ventanilla_id', 'user_id');
    }

    // Relación con los tipos documentales permitidos en la ventanilla
    public function tiposDocumentales()
    {
        return $this->belongsToMany(ClasificacionDocumentalTRD::class, 'ventanilla_tipos_documentales', 'ventanilla_id', 'tipo_documental_id');
    }
}
