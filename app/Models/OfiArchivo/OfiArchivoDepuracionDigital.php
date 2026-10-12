<?php

namespace App\Models\OfiArchivo;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;

/**
 * M08-02: bitácora de archivos digitales huérfanos movidos a cuarentena, restaurados o purgados.
 *
 * @property int $id
 * @property string $disco
 * @property string $accion cuarentena|restaurado|purgado
 * @property string $ruta_original
 * @property string|null $ruta_cuarentena
 * @property string|null $hash_sha256
 * @property int|null $tamano_bytes
 * @property string $origen
 * @property int|null $user_id
 */
class OfiArchivoDepuracionDigital extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ofi_archivo_depuraciones_digitales';

    protected $fillable = [
        'disco', 'accion', 'ruta_original', 'ruta_cuarentena', 'hash_sha256', 'tamano_bytes', 'origen', 'user_id',
    ];

    /**
     * Registra una acción sobre un archivo; la huella y el tamaño se toman de `$rutaActual`
     * (donde está el archivo en el momento de registrar).
     */
    public static function registrar(
        string $disco,
        string $accion,
        string $rutaOriginal,
        ?string $rutaCuarentena,
        Filesystem $storage,
        string $rutaActual,
        string $origen,
    ): self {
        $existe = $storage->exists($rutaActual);
        $stream = $existe ? $storage->readStream($rutaActual) : null;
        $hash = null;
        if ($stream) {
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $stream);
            fclose($stream);
            $hash = hash_final($ctx);
        }

        return self::create([
            'disco' => $disco,
            'accion' => $accion,
            'ruta_original' => $rutaOriginal,
            'ruta_cuarentena' => $rutaCuarentena,
            'hash_sha256' => $hash,
            'tamano_bytes' => $existe ? $storage->size($rutaActual) : null,
            'origen' => $origen,
            'user_id' => auth()->id(),
        ]);
    }
}
