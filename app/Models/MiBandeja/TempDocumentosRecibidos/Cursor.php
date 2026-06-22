<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cursor colaborativo de un usuario en un documento.
 *
 * Representa la posición y selección del cursor de cada usuario
 * en tiempo real para visualización compartida.
 *
 * @property int $id
 * @property int $documento_id ID del documento
 * @property int $user_id ID del usuario
 * @property string $nombre_usuario Nombre visible del usuario
 * @property string $color Color del cursor (hex)
 * @property int $posicion Posición del cursor en el documento
 * @property string|null $seleccion_inicio Inicio de selección
 * @property string|null $seleccion_fin Fin de selección
 * @property Carbon $ultima_actividad Última actualización
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Documento $documento
 * @property-read User $usuario
 */
class Cursor extends Model
{
    /** @var string Nombre de la tabla */
    protected $table = 'mi_bandeja_temp_reci_cursores';

    /** @var array<string> Campos asignables en masa */
    protected $fillable = [
        'documento_id',
        'user_id',
        'nombre_usuario',
        'color',
        'posicion',
        'seleccion_inicio',
        'seleccion_fin',
        'ultima_actividad',
    ];

    /** @var array<string, mixed> Conversiones de tipos */
    protected $casts = [
        'ultima_actividad' => 'datetime',
    ];

    /**
     * Relación con el documento.
     *
     * @return BelongsTo<Documento, Cursor>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Relación con el usuario.
     *
     * @return BelongsTo<User, Cursor>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Actualiza la posición del cursor.
     *
     * @param  int  $posicion  Nueva posición
     * @param  string|null  $inicio  Inicio de selección
     * @param  string|null  $fin  Fin de selección
     */
    public function actualizarPosicion(int $posicion, ?string $inicio = null, ?string $fin = null): void
    {
        $this->posicion = $posicion;
        $this->seleccion_inicio = $inicio;
        $this->seleccion_fin = $fin;
        $this->ultima_actividad = now();
        $this->save();
    }

    /**
     * Verifica si el cursor está activo (actividad reciente).
     *
     * @return bool true si está activo (< 30 segundos)
     */
    public function esActivo(): bool
    {
        return $this->ultima_actividad &&
            $this->ultima_actividad->diffInSeconds(now()) < 30;
    }

    /**
     * Convierte a array para broadcasting.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'nombre_usuario' => $this->nombre_usuario,
            'color' => $this->color,
            'posicion' => $this->posicion,
            'seleccion_inicio' => $this->seleccion_inicio,
            'seleccion_fin' => $this->seleccion_fin,
            'ultima_actividad' => $this->ultima_actividad?->toISOString(),
        ];
    }
}
