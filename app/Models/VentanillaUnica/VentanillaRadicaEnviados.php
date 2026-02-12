<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviados extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados';

    protected $fillable = [
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'tercero_enviado_id',
        'medio_enviado_id',
        'config_server_id',
        'tipo_respuesta_id',
        'subido_por',
        'fec_docu',
        'num_folios',
        'num_anexos',
        'descrip_anexos',
        'asunto',
        'radicado_respuesta',
        'archivo_digital',
        'impri_rotulo',
    ];

    /**
     * Clasificación documental (recursiva: Serie > SubSerie > TipoDocumento).
     * Para cargar con jerarquía completa usar: load(['clasificacionDocumental' => fn($q) => $q->with(['parent' => fn($q) => $q->with('parent')])])
     */
    public function clasificacionDocumental()
    {
        return $this->belongsTo(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'clasifica_documen_id');
    }

    /**
     * Carga clasificación documental con jerarquía completa (evita N+1 en getJerarquia).
     */
    public function loadClasificacionConJerarquia()
    {
        return $this->load(['clasificacionDocumental' => fn($q) => $q->with(['parent' => fn($q) => $q->with('parent')])]);
    }

    /**
     * Obtiene la clasificación documental con su jerarquía recursiva.
     */
    public function getClasificacionDocumentalInfo(): ?array
    {
        $clasif = $this->clasificacionDocumental;
        if (!$clasif) {
            return null;
        }
        return [
            'id' => $clasif->id,
            'cod' => $clasif->cod,
            'nom' => $clasif->nom,
            'tipo' => $clasif->tipo,
            'jerarquia' => $clasif->getJerarquia(),
            'codigo_completo' => $clasif->getCodigoCompleto(),
            'nombre_completo' => $clasif->getNombreCompleto(),
        ];
    }

    public function usuarioCreaRadicado()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
    }

    public function tercero()
    {
        return $this->belongsTo(\App\Models\Gestion\GestionTercero::class, 'tercero_enviado_id');
    }

    public function medioEnvio()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'medio_enviado_id');
    }

    public function servidorArchivos()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigServerArchivo::class, 'config_server_id');
    }

    public function tipoRespuesta()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'tipo_respuesta_id');
    }

    public function usuarioSubio()
    {
        return $this->belongsTo(\App\Models\User::class, 'subido_por');
    }

    public function responsables()
    {
        return $this->hasMany(VentanillaRadicaEnviadosRespona::class, 'radica_enviado_id');
    }

    public function usuariosResponsables()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_responsa', 'radica_enviado_id', 'users_cargos_id')
            ->withPivot('custodio', 'fechor_visto')
            ->withTimestamps();
    }

    public function respuestas()
    {
        return $this->hasMany(VentanillaRadicaEnviadosRespuestas::class, 'radica_enviado_id');
    }

    public function usuariosRespuestas()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_respuestas', 'radica_enviado_id', 'users_cargos_id')
            ->withTimestamps();
    }

    public function firmas()
    {
        return $this->hasMany(VentanillaRadicaEnviadosFirmas::class, 'radica_enviado_id');
    }

    public function usuariosFirmas()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_firmas', 'radica_enviado_id', 'users_cargos_id')
            ->withTimestamps();
    }

    public function proyectores()
    {
        return $this->hasMany(VentanillaRadicaEnviadosProyectores::class, 'radica_enviado_id');
    }

    public function usuariosProyectores()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_proyectores', 'radica_enviado_id', 'users_cargos_id')
            ->withTimestamps();
    }

    public function getResponsablesInfo(): array
    {
        $responsablesRelacion = $this->relationLoaded('responsables') ? $this->responsables : $this->responsables()->with(['userCargo.user', 'userCargo.cargo'])->get();

        $responsablesInfo = collect();
        $totalCustodios = 0;

        foreach ($responsablesRelacion as $responsable) {
            $info = $responsable->getInfoResponsable();
            if ($info) {
                $responsablesInfo->push($info);
                if (!empty($info['custodio']) && $info['custodio']) {
                    $totalCustodios++;
                }
            }
        }

        return [
            'responsables' => $responsablesInfo,
            'total_responsables' => $responsablesInfo->count(),
            'total_custodios' => $totalCustodios,
        ];
    }

    public function getInfoUsuarioCrea(): ?array
    {
        return $this->usuarioCreaRadicado?->getInfoUsuario();
    }

    public function getInfoUsuarioSubio(): ?array
    {
        return $this->usuarioSubio?->getInfoUsuario();
    }

    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaEnviadosArchivos::class, 'radica_enviado_id');
    }

    public function getArchivosInfo(bool $incluirMetadatos = false): array
    {
        $archivosRelacion = $this->relationLoaded('archivos') ? $this->archivos : $this->archivos()->with(['usuarioSubio'])->get();

        $archivosInfo = [];
        foreach ($archivosRelacion as $archivo) {
            $info = $archivo->getInfoArchivo($incluirMetadatos);
            if ($info) {
                $archivosInfo[] = $info;
            }
        }
        return $archivosInfo;
    }
}
