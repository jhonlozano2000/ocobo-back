<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión guardada de un documento colaborativo.
 *
 * Permite restaurar estados anteriores del documento.
 * Cada versión Incrementa el número de forma secuencial.
 *
 * @property int $id
 * @property int $documento_id ID del documento
 * @property int $user_id ID del usuario que creó la versión
 * @property array $contenido_yjs Estado Yjs en esta versión
 * @property string|null $hash_contenido Hash del contenido
 * @property string|null $descripcion Descripción opcional
 * @property int $numero_version Número secuencial de versión
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Documento $documento
 * @property-read User $usuario
 */
class Version extends Model
{
    /** @var int Número máximo de versiones a mantener */
    public const MAX_VERIONES = 50;

    /** @var string Nombre de la tabla */
    protected $table = 'mi_bandeja_temp_reci_versiones';

    /** @var array<string> Campos asignables en masa */
    protected $fillable = [
        'documento_id',
        'user_id',
        'contenido_yjs',
        'hash_contenido',
        'descripcion',
        'numero_version',
    ];

    /** @var array<string, mixed> Conversiones de tipos */
    protected $casts = [
        'contenido_yjs' => 'array',
    ];

    /**
     * Relación con el documento.
     *
     * @return BelongsTo<Documento, Version>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Relación con el usuario que creó la versión.
     *
     * @return BelongsTo<User, Version>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Crea una nueva versión del documento.
     *
     * Solo crea versión si el contenido es diferente al de la última versión.
     * Además mantiene un límite máximo de versiones (50).
     *
     * @param  Documento  $documento  Documento a versionar
     * @param  array  $contenido  Contenido Yjs actual
     * @param  User  $user  Usuario que crea la versión
     * @param  string|null  $descripcion  Descripción opcional
     * @return static|null Retorna la versión creada o null si el contenido no cambió
     */
    public static function crearVersion(Documento $documento, array $contenido, User $user, ?string $descripcion = null): ?Version
    {
        $nuevoHash = hash('sha256', json_encode($contenido));
        $ultimaVersion = $documento->versiones()->first();

        if ($ultimaVersion && $ultimaVersion->hash_contenido === $nuevoHash) {
            return null;
        }

        $version = self::create([
            'documento_id' => $documento->id,
            'user_id' => $user->id,
            'contenido_yjs' => $contenido,
            'hash_contenido' => $nuevoHash,
            'descripcion' => $descripcion,
            'numero_version' => ($ultimaVersion?->numero_version ?? 0) + 1,
        ]);

        $documento->limitarVersiones();

        return $version;
    }

    /**
     * Limita el número de versiones de un documento.
     *
     * Elimina las versiones más antiguas si excede MAX_VERIONES.
     *
     * @param  Documento  $documento  Documento a limitar
     * @param  int  $maxVersiones  Número máximo de versiones (default: MAX_VERIONES)
     * @return int Número de versiones eliminadas
     */
    public static function limitarVersiones(Documento $documento, int $maxVersiones = self::MAX_VERIONES): int
    {
        $totalVersiones = $documento->versiones()->count();

        if ($totalVersiones <= $maxVersiones) {
            return 0;
        }

        $aEliminar = $totalVersiones - $maxVersiones;

        $versionesAEliminar = $documento->versiones()
            ->orderBy('numero_version', 'asc')
            ->limit($aEliminar)
            ->pluck('id');

        return self::whereIn('id', $versionesAEliminar)->delete();
    }

    /**
     * Restaura el contenido de esta versión.
     *
     * @return array Contenido Yjs
     */
    public function restaurar(): array
    {
        return $this->contenido_yjs ?? [];
    }
}
