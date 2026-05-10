<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comentario en un documento colaborativo.
 *
 * Puede tener respuestas (comentarios anidados).
 * Soporta selección de texto para contexto.
 *
 * @property int $id
 * @property int $documento_id ID del documento
 * @property int $user_id ID del usuario autor
 * @property int|null $parent_id ID del comentario padre (respuesta)
 * @property string $contenido Texto del comentario
 * @property array|null $seleccion_texto Selección de texto contextual
 * @property bool $resuelto Si está marcado como resuelto
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Documento $documento
 * @property-read User $usuario
 * @property-read Comentario|null $respuesta
 * @property-read HasMany<Comentario> $respuestas
 */
class Comentario extends Model
{
    /** @var string Nombre de la tabla */
    protected $table = 'mi_bandeja_temp_reci_comentarios';

    /** @var array<string> Campos asignables en masa */
    protected $fillable = [
        'documento_id',
        'user_id',
        'parent_id',
        'contenido',
        'seleccion_texto',
        'resuelto',
    ];

    /** @var array<string, mixed> Conversiones de tipos */
    protected $casts = [
        'seleccion_texto' => 'array',
        'resuelto' => 'boolean',
    ];

    /**
     * Relación con el documento.
     *
     * @return BelongsTo<Documento, Comentario>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Relación con el usuario autor.
     *
     * @return BelongsTo<User, Comentario>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el comentario padre.
     *
     * @return BelongsTo<Comentario, Comentario>
     */
    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(Comentario::class, 'parent_id');
    }

    /**
     * Relación con las respuestas.
     *
     * @return HasMany<Comentario>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(Comentario::class, 'parent_id');
    }

    /**
     * Marca el comentario como resuelto.
     *
     * @return void
     */
    public function marcarResuelto(): void
    {
        $this->resuelto = true;
        $this->save();
    }

    /**
     * Resuelve el comentario y todas sus respuestas.
     *
     * @return void
     */
    public function resolver(): void
    {
        $this->marcarResuelto();

        if ($this->respuestas) {
            foreach ($this->respuestas as $respuesta) {
                $respuesta->marcarResuelto();
            }
        }
    }
}
