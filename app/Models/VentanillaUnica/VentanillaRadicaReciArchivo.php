<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ArchivoHelper;

class VentanillaRadicaReciArchivo extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_archivos';

    protected $fillable = [
        'radicado_id',
        'subido_por',
        'archivo',
        'nom_origi',
        'archivo_peso',
        'hash_sha256',
    ];

    /**
     * Usuario que subió el archivo adjunto.
     */
    public function usuarioSubido()
    {
        return $this->belongsTo(\App\Models\User::class, 'subido_por');
    }

    /**
     * Metadatos ISO 27001 del archivo.
     */
    public function metadata()
    {
        return $this->hasOne(VentanillaRadicaReciMetadata::class, 'archivo_id');
    }

    /**
     * Radicado al que pertenece el archivo.
     */
    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    /**
     * Obtiene la URL de cualquier archivo usando ArchivoHelper.
     * @param string $campo Nombre del atributo (ej: 'archivo')
     * @param string $disk Nombre del disco
     * @return string|null
     */
    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }

    /**
     * Obtiene información básica de un archivo (sin acceder al filesystem).
     * Los metadatos del archivo (tamaño, tipo) se obtienen solo al descargar/ver detalles.
     *
     * @param string $campo Nombre del atributo del archivo
     * @param string $disk Nombre del disco
     * @param bool $incluirMetadatos Si es true, obtiene metadatos del filesystem (por defecto false)
     * @return array|null
     */
    public function getInfoArchivo(string $campo, string $disk, bool $incluirMetadatos = false): ?array
    {
        $rutaArchivo = $this->{$campo} ?? null;
        if (!$rutaArchivo) {
            return null;
        }

        $info = [
            'id' => $this->id,
            'nombre' => $this->nom_origi ?: basename($rutaArchivo),
            'ruta' => $rutaArchivo,
            'url' => $this->getArchivoUrl($campo, $disk),
            'fecha_subida' => $this->created_at,
            'extension' => pathinfo($rutaArchivo, PATHINFO_EXTENSION),
        ];

        // Usar peso guardado si existe, o acceder al filesystem si se solicita explícitamente
        if ($this->archivo_peso) {
            $info['tamaño'] = $this->archivo_peso;
        } elseif ($incluirMetadatos) {
            try {
                if (Storage::disk($disk)->exists($rutaArchivo)) {
                    $info['tamaño'] = Storage::disk($disk)->size($rutaArchivo);
                }
            } catch (\Exception $e) {
                // Si hay error al obtener información del archivo, continuar sin esos datos
            }
        }

        if ($incluirMetadatos && !$this->archivo_peso) {
            try {
                if (Storage::disk($disk)->exists($rutaArchivo)) {
                    $info['tipo'] = Storage::disk($disk)->mimeType($rutaArchivo);
                }
            } catch (\Exception $e) {
                // Si hay error al obtener información del archivo, continuar sin esos datos
            }
        }

        return $info;
    }
}
