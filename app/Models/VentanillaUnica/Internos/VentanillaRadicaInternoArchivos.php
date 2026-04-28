<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Helpers\ArchivoHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInternoArchivos extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_interno_archivos';

    protected $fillable = [
        'radicado_id',
        'subido_por',
        'archivo',
        'nom_origi',
        'archivo_tipo',
        'archivo_peso',
        'hash_sha256',
    ];

    public function radicaInterno()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radicado_id');
    }

    public function metadata()
    {
        return $this->hasOne(VentanillaRadicaInternoMetadata::class, 'archivo_id');
    }

    public function usuarioSubido()
    {
        return $this->belongsTo(\App\Models\User::class, 'subido_por');
    }

    public function getArchivoUrl(): ?string
    {
        return ArchivoHelper::obtenerUrl($this->archivo, 'ventanilla_radica_interno_archivos');
    }

    public function getInfoArchivo(bool $incluirMetadatos = false): ?array
    {
        if (!$this->archivo) {
            return null;
        }
        $info = [
            'id' => $this->id,
            'nombre' => $this->nom_origi ?? basename($this->archivo),
            'ruta' => $this->archivo,
            'url' => $this->getArchivoUrl(),
            'fecha_subida' => $this->created_at,
            'extension' => pathinfo($this->archivo, PATHINFO_EXTENSION),
        ];
        if ($incluirMetadatos) {
            try {
                if (Storage::disk('ventanilla_radica_interno_archivos')->exists($this->archivo)) {
                    $info['archivo_peso'] = Storage::disk('ventanilla_radica_interno_archivos')->size($this->archivo);
                    $info['tipo'] = Storage::disk('ventanilla_radica_interno_archivos')->mimeType($this->archivo);
                }
            } catch (\Exception $e) {
            }
        }
        return $info;
    }
}
