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
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'tercero_id',
        'medio_recep_id',
        'config_server_id',
        'fec_venci',
        'fec_docu',
        'num_folios',
        'num_anexos',
        'descrip_anexos',
        'asunto',
        'radicado_respuesta',
        'archivo_digital',
        'uploaded_by',
        'impri_rotulo',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($radicado) {
            // Usar ArchivoHelper para eliminar el archivo si existe
            if ($radicado->archivo_digital) {
            ArchivoHelper::eliminarArchivo($radicado->archivo_digital, 'radicaciones_recibidas');
        }
        });
    }

    public function clasificacionDocumental()
    {
        return $this->belongsTo(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'clasifica_documen_id');
    }

    public function usuarioCreaRadicado()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
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
     * Obtiene los usuarios responsables a través de la tabla pivot con users_cargos.
     */
    public function usuariosResponsables()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_reci_responsa', 'radica_reci_id', 'users_cargos_id')
            ->withPivot('custodio', 'fechor_visto')
            ->withTimestamps();
    }

    /**
     * Obtiene los archivos adicionales asociados al radicado.
     */
    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaReciArchivo::class, 'radicado_id');
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
    public function tieneArchivoDigital()
    {
        return !empty($this->archivo_digital);
    }

    /**
     * Verifica si la radicación tiene archivos (digital o adicionales).
     */
    public function tieneArchivos()
    {
        return $this->tieneArchivoDigital() || $this->archivos()->exists();
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
    public function getUrlArchivoDigital()
    {
        return ArchivoHelper::obtenerUrl($this->archivo_digital, 'radicaciones_recibidas');
    }

    /**
     * Obtiene información del archivo asociado a la radicación.
     *
     * @return array|null
     */
    public function getInfoArchivoDigital()
    {
        if (!$this->archivo_digital) {
            return null;
        }

        return [
            'nombre' => basename($this->archivo_digital),
            'url' => $this->getUrlArchivoDigital(),
            'tamaño' => \Storage::disk('radicaciones_recibidas')->size($this->archivo_digital),
            'tipo' => \Storage::disk('radicaciones_recibidas')->mimeType($this->archivo_digital),
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION)
        ];
    }
}
