<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contenido Yjs de un documento colaborativo.
 *
 * Almacena el estado CRDT del documento en formato JSON.
 * Incluye hash SHA256 para detección de cambios.
 *
 * @property int $id
 * @property int $documento_id ID del documento
 * @property array $contenido_yjs Estado Yjs serializado
 * @property string|null $hash_contenido Hash SHA256 del contenido
 * @property int|null $actualizado_por Usuario que actualizó
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Documento $documento
 * @property-read User|null $actualizadoPor
 */
class Contenido extends Model
{
    /** @var string Nombre de la tabla */
    protected $table = 'mi_bandeja_temp_reci_contenido';

    /** @var array<string> Campos asignables en masa */
    protected $fillable = [
        'documento_id',
        'contenido_yjs',
        'hash_contenido',
        'actualizado_por',
    ];

    /** @var array<string, mixed> Conversiones de tipos */
    protected $casts = [
        'contenido_yjs' => 'array',
    ];

    /**
     * Relación con el documento.
     *
     * @return BelongsTo<Documento, Contenido>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Relación con el usuario que actualizó.
     *
     * @return BelongsTo<User, Contenido>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /**
     * Actualiza el contenido y regenera el hash.
     *
     * @param  array  $contenido  Nuevo contenido Yjs
     * @param  User  $user  Usuario que actualiza
     */
    public function actualizarContenido(array $contenido, User $user): void
    {
        $this->contenido_yjs = $contenido;
        $this->hash_contenido = hash('sha256', json_encode($contenido));
        $this->actualizado_por = $user->id;
        $this->save();
    }

    /**
     * Verifica si el contenido ha cambiado.
     *
     * @param  array  $nuevoContenido  Contenido a comparar
     * @return bool true si es diferente
     */
    public function haCambiado(array $nuevoContenido): bool
    {
        return $this->hash_contenido !== hash('sha256', json_encode($nuevoContenido));
    }
}
