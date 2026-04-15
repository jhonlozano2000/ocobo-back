<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentanillaRadicaEnviadosMetadataHistory extends Model
{
    protected $table = 'ventanilla_radica_enviados_metadata_history';

    protected $fillable = [
        'metadata_id',
        'tipo_cambio',
        'campo_modificado',
        'valor_anterior',
        'valor_nuevo',
        'usuario_id',
        'usuario_nombre',
        'ip_address',
        'user_agent',
        'fecha_cambio',
    ];

    protected $casts = [
        'fecha_cambio' => 'datetime',
    ];

    public function metadata(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaEnviadosMetadata::class, 'metadata_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    public static function registrarCreacion(int $metadataId, array $datos, ?int $usuarioId = null, ?string $usuarioNombre = null): self
    {
        return self::create([
            'metadata_id' => $metadataId,
            'tipo_cambio' => 'CREACION',
            'campo_modificado' => null,
            'valor_anterior' => null,
            'valor_nuevo' => json_encode($datos),
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fecha_cambio' => now(),
        ]);
    }

    public static function registrarActualizacion(int $metadataId, array $cambios, ?int $usuarioId = null, ?string $usuarioNombre = null): self
    {
        $registros = [];
        foreach ($cambios as $campo => $valor) {
            $registros[] = [
                'metadata_id' => $metadataId,
                'tipo_cambio' => 'ACTUALIZACION',
                'campo_modificado' => $campo,
                'valor_anterior' => $valor['anterior'] ?? null,
                'valor_nuevo' => $valor['nuevo'] ?? null,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'fecha_cambio' => now(),
            ];
        }

        return self::insert($registros) ? self::where('metadata_id', $metadataId)->latest()->first() : null;
    }

    public static function registrarEliminacion(int $metadataId, array $datos, ?int $usuarioId = null, ?string $usuarioNombre = null): self
    {
        return self::create([
            'metadata_id' => $metadataId,
            'tipo_cambio' => 'ELIMINACION',
            'campo_modificado' => null,
            'valor_anterior' => json_encode($datos),
            'valor_nuevo' => null,
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fecha_cambio' => now(),
        ]);
    }

    public static function registrarConsulta(int $metadataId, ?int $usuarioId = null, ?string $usuarioNombre = null): self
    {
        return self::create([
            'metadata_id' => $metadataId,
            'tipo_cambio' => 'CONSULTA',
            'campo_modificado' => null,
            'valor_anterior' => null,
            'valor_nuevo' => null,
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fecha_cambio' => now(),
        ]);
    }
}
