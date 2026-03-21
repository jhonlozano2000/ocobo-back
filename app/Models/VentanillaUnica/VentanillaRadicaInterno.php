<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInterno extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_internos';

    protected $fillable = [
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'fec_docu',
        'fec_venci',
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
     * Usuario que creó la radicación interna.
     */
    public function usuarioCrea()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
    }

    /**
     * Destinatarios de la radicación interna.
     */
    public function destinatarios()
    {
        return $this->hasMany(VentanillaRadicaInternoDestinatarios::class, 'radica_interno_id');
    }

    /**
     * Responsables de la radicación interna.
     */
    public function responsables()
    {
        return $this->hasMany(VentanillaRadicaInternoResponsa::class, 'radica_interno_id');
    }

    /**
     * Proyectores de la radicación interna.
     */
    public function proyectores()
    {
        return $this->hasMany(VentanillaRadicaInternoProyectores::class, 'radica_interno_id');
    }

    /**
     * Archivos de la radicación interna.
     */
    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaInternoArchivos::class, 'radica_interno_id');
    }

    /**
     * Obtiene la línea de tiempo de la radicación interna.
     */
    public function getLineaTiempo(): array
    {
        return [
            'fecha_creacion' => $this->created_at,
            'fecha_actualizacion' => $this->updated_at,
            'fecha_eliminacion' => $this->deleted_at,
        ];
    }

    /**
     * Obtiene los documentos relacionados con la radicación interna.
     */
    public function getDocumentosRelacionados(bool $incluirMetadatos = false): array
    {
        $documentos = [];
        foreach ($this->archivos as $archivo) {
            $documentos[] = $archivo->getInfoArchivo($incluirMetadatos);
        }
        return $documentos;
    }

    /**
     * Carga la clasificación documental con su jerarquía recursiva.
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

    /**
     * Obtiene la información de un archivo de la radicación interna.
     */
    public function getInfoArchivo(bool $incluirMetadatos = false): ?array
    {
        $info = [
            'nombre' => basename($this->archivo_digital),
            'ruta' => $this->archivo_digital,
            'url' => $this->getArchivoUrl(),
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION),
        ];

        // Solo acceder al filesystem si se solicita explícitamente (para descarga/detalles)
        if ($incluirMetadatos) {
            try {
                if (Storage::disk('ventanilla_radica_interno_archivos')->exists($this->archivo_digital)) {
                    $info['tamaño'] = Storage::disk('ventanilla_radica_interno_archivos')->size($this->archivo_digital);
                    $info['tipo'] = Storage::disk('ventanilla_radica_interno_archivos')->mimeType($this->archivo_digital);
                }
            } catch (\Exception $e) {
                // Si hay error al obtener información del archivo, continuar sin esos datos
            }
        }

        return $info;
    }
}
