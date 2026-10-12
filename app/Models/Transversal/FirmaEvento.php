<?php

namespace App\Models\Transversal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmaEvento extends Model
{
    use HasFactory;

    protected $table = 'firmas_eventos';

    protected $fillable = [
        'documentable_id',
        'documentable_type',
        'user_id',
        'hash_original',
        'hash_firmado',
        'timestamp_token',
        'timestamp_fecha',
        'otp_utilizado',
        'ip_address',
        'user_agent',
        'fecha_firma',
    ];

    protected $casts = [
        'fecha_firma' => 'datetime',
        'timestamp_fecha' => 'datetime',
    ];

    /**
     * M06-10: Inmutabilidad WORM a nivel de modelo.
     * Los eventos de firma electrónica constituyen trazabilidad legal y no pueden mutar ni eliminarse.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Los eventos de firma electrónica son inmutables (WORM) y no pueden modificarse.');
        });

        static::deleting(function () {
            throw new \DomainException('Los eventos de firma electrónica son inmutables (WORM) y no pueden eliminarse.');
        });
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * M06-12: las firmas de un documento (firmantes, correos, hashes) solo las consulta quien tiene acceso
     * al documento con el mismo alcance jerárquico de los listados, o quien firmó en él.
     *
     * @param  class-string  $modelClass  Modelo con el scope conPermisoJerarquico
     */
    public static function usuarioPuedeConsultar(string $modelClass, int $documentoId, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $modelClass::conPermisoJerarquico($user)->whereKey($documentoId)->exists()
            || static::where('documentable_type', $modelClass)
                ->where('documentable_id', $documentoId)
                ->where('user_id', $user->id)
                ->exists();
    }

    /**
     * El PDF quedó estampado: la firma generó una versión nueva con hash propio (M06-11).
     * No prueba integridad; eso lo hace FirmaValidacionService recalculando el hash del archivo.
     */
    public function estaEstampado(): bool
    {
        return ! empty($this->hash_firmado) && $this->hash_firmado !== $this->hash_original;
    }
}
