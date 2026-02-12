<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ArchivoHelper;

class VentanillaRadicaEnviadosArchivos extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_archivos';

    protected $fillable = [
        'radica_enviado_id',
        'subido_por',
        'archivo',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radica_enviado_id');
    }

    public function usuarioSubio()
    {
        return $this->belongsTo(\App\Models\User::class, 'subido_por');
    }

    public function getArchivoUrl(string $disk = 'radicados_enviados'): ?string
    {
        return ArchivoHelper::obtenerUrl($this->archivo ?? null, $disk);
    }

    public function getInfoArchivo(bool $incluirMetadatos = false): ?array
    {
        if (!$this->archivo) {
            return null;
        }
        $info = [
            'id' => $this->id,
            'nombre' => basename($this->archivo),
            'ruta' => $this->archivo,
            'url' => $this->getArchivoUrl(),
            'fecha_subida' => $this->created_at,
            'extension' => pathinfo($this->archivo, PATHINFO_EXTENSION),
        ];
        if ($incluirMetadatos) {
            try {
                if (Storage::disk('radicados_enviados')->exists($this->archivo)) {
                    $info['tamaño'] = Storage::disk('radicados_enviados')->size($this->archivo);
                    $info['tipo'] = Storage::disk('radicados_enviados')->mimeType($this->archivo);
                }
            } catch (\Exception $e) {
            }
        }
        return $info;
    }
}
