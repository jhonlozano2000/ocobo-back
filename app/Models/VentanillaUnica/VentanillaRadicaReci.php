<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaReci extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci';

    protected $fillable = [
        'clasifica_documen_id',
        'tercero_id',
        'medio_recep_id',
        'config_server_id',
        'fec_venci',
        'num_folios',
        'num_anexos',
        'descrip_anexos',
        'asunto',
        'archivo_radica',
        'num_radicado',
        'uploaded_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($radicado) {
            // Eliminar el archivo si existe
            if ($radicado->archivo_radica && Storage::exists($radicado->archivo_radica)) {
                Storage::delete($radicado->archivo_radica);
            }
        });
    }

    public function clasificacionDocumental()
    {
        return $this->belongsTo(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'clasifica_documen_id');
    }

    public function tercero()
    {
        return $this->belongsTo(\App\Models\Gestion\GestionTercero::class, 'tercero_id');
    }

    public function medioRecepcion()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'medio_recep_id');
    }

    public function servidorArchivos()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigServerArchivo::class, 'config_server_id');
    }

    public function usuarioSubio()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
