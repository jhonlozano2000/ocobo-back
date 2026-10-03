<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Helpers\ArchivoHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInternoArchivos extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_internos_archivos';

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

    public function radicado()
    {
        return $this->radicaInterno();
    }

    public function metadata()
    {
        return $this->hasOne(VentanillaRadicaInternoMetadata::class, 'archivo_id');
    }

    public function usuarioSubido()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function getArchivoUrl(): ?string
    {
        return ArchivoHelper::obtenerUrl($this->archivo, 'radicados_internos');
    }

    public function getInfoArchivo(bool $incluirMetadatos = false): ?array
    {
        if (! $this->archivo) {
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
                if (Storage::disk('radicados_internos')->exists($this->archivo)) {
                    $info['archivo_peso'] = Storage::disk('radicados_internos')->size($this->archivo);
                    $info['tipo'] = Storage::disk('radicados_internos')->mimeType($this->archivo);
                }
            } catch (\Exception $e) {
            }
        }

        return $info;
    }
}
