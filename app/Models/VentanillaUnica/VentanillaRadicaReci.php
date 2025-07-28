<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\ArchivoHelper;

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
            // Usar ArchivoHelper para eliminar el archivo si existe
            if ($radicado->archivo_radica) {
                ArchivoHelper::eliminarArchivo($radicado->archivo_radica, 'radicaciones_recibidas');
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

    /**
     * Obtiene los responsables asignados a esta radicación.
     */
    public function responsables()
    {
        return $this->hasMany(VentanillaRadicaReciResponsa::class, 'radica_reci_id');
    }

    /**
     * Obtiene los usuarios responsables a través de la tabla pivot.
     */
    public function usuariosResponsables()
    {
        return $this->belongsToMany(\App\Models\User::class, 'ventanilla_radica_reci_responsa', 'radica_reci_id', 'user_id')
            ->withPivot('custodio')
            ->withTimestamps();
    }

    /**
     * Scope para filtrar por estado activo.
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', true);
    }

    /**
     * Scope para filtrar por estado inactivo.
     */
    public function scopeInactivo($query)
    {
        return $query->where('estado', false);
    }

    /**
     * Verifica si la radicación tiene archivos.
     */
    public function tieneArchivos()
    {
        return !empty($this->archivo_radica);
    }

    /**
     * Obtiene los días restantes para el vencimiento.
     */
    public function getDiasParaVencerAttribute()
    {
        if (!$this->fec_venci) {
            return null;
        }
        return now()->diffInDays($this->fec_venci, false);
    }

    /**
     * Verifica si la radicación está vencida.
     */
    public function isVencida()
    {
        if (!$this->fec_venci) {
            return false;
        }
        return now()->isAfter($this->fec_venci);
    }

    /**
     * Obtiene la URL del archivo asociado a la radicación.
     *
     * @return string|null
     */
    public function getArchivoUrlAttribute()
    {
        return ArchivoHelper::obtenerUrl($this->archivo_radica, 'radicaciones_recibidas');
    }

    /**
     * Obtiene información del archivo asociado a la radicación.
     *
     * @return array|null
     */
    public function getArchivoInfoAttribute()
    {
        if (!$this->archivo_radica) {
            return null;
        }

        return [
            'nombre' => basename($this->archivo_radica),
            'url' => $this->archivo_url,
            'tamaño' => \Storage::disk('radicaciones_recibidas')->size($this->archivo_radica),
            'tipo' => \Storage::disk('radicaciones_recibidas')->mimeType($this->archivo_radica),
            'extension' => pathinfo($this->archivo_radica, PATHINFO_EXTENSION)
        ];
    }
}
