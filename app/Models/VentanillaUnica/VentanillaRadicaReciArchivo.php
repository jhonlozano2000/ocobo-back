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
    ];

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
            'nombre' => basename($rutaArchivo),
            'ruta' => $rutaArchivo,
            'url' => $this->getArchivoUrl($campo, $disk),
            'fecha_subida' => $this->created_at,
            'extension' => pathinfo($rutaArchivo, PATHINFO_EXTENSION),
        ];

        // Solo acceder al filesystem si se solicita explícitamente (para descarga/detalles)
        if ($incluirMetadatos) {
            try {
                if (Storage::disk($disk)->exists($rutaArchivo)) {
                    $info['tamaño'] = Storage::disk($disk)->size($rutaArchivo);
                    $info['tipo'] = Storage::disk($disk)->mimeType($rutaArchivo);
                }
            } catch (\Exception $e) {
                // Si hay error al obtener información del archivo, continuar sin esos datos
            }
        }

        return $info;
    }
}
