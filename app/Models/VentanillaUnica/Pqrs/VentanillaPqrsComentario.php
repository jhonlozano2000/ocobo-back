<?php

namespace App\Models\VentanillaUnica\Pqrs;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo VentanillaPqrsComentario - Comentarios threaded en PQRS
 *
 * Implementa un sistema de comentarios con hilos (threaded) para colaboración
 * en radicados PQRS. Soporta respuestas anidadas (parent_id), notas internas
 * (visibles solo para personal interno), selección de texto para referenciar
 * partes del documento, y marcado de resuelto.
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $contenido
 * @property array|null $seleccion_texto
 * @property bool $resuelto
 * @property bool $es_nota_interna
 * @property int|null $resuelto_por
 * @property \Carbon\Carbon|null $fecha_resolucion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read VentanillaPqrs $pqrs
 * @property-read User $usuario
 * @property-read self|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, self> $respuestas
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsComentario extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_comentarios';

    /**
     * Atributos asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pqrs_id',
        'user_id',
        'parent_id',
        'contenido',
        'seleccion_texto',
        'resuelto',
        'es_nota_interna',
        'resuelto_por',
        'fecha_resolucion',
    ];

    /**
     * Conversión automática de tipos de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'resuelto' => 'boolean',
        'es_nota_interna' => 'boolean',
        'seleccion_texto' => 'array',
        'fecha_resolucion' => 'datetime',
    ];

    /**
     * Relación: PQRS al que pertenece este comentario.
     */
    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'pqrs_id');
    }

    /**
     * Relación: Usuario autor del comentario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación: Comentario padre (para hilos/respuestas anidadas).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Relación: Respuestas/hijos de este comentario.
     *
     * @return HasMany<self>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Marca el comentario como resuelto.
     *
     * @param int|null $userId ID del usuario que resuelve (por defecto usuario autenticado)
     * @return void
     */
    public function resolver(?int $userId = null): void
    {
        $this->update([
            'resuelto' => true,
            'resuelto_por' => $userId ?? auth()->id(),
            'fecha_resolucion' => now(),
        ]);
    }

    /**
     * Obtiene representación estructurada del comentario con sus respuestas recursivas.
     *
     * @return array{
     *     id: int,
     *     contenido: string,
     *     seleccion_texto: array|null,
     *     resuelto: bool,
     *     es_nota_interna: bool,
     *     usuario: array{id: int, name: string, email: string}|null,
     *     fecha: \Carbon\Carbon,
     *     respuestas: array<int, array>
     * }
     */
    public function getInfo(): array
    {
        return [
            'id' => $this->id,
            'contenido' => $this->contenido,
            'seleccion_texto' => $this->seleccion_texto,
            'resuelto' => $this->resuelto,
            'es_nota_interna' => $this->es_nota_interna,
            'usuario' => $this->usuario?->only(['id', 'name', 'email']),
            'fecha' => $this->created_at,
            'respuestas' => $this->respuestas->map->getInfo()->toArray(),
        ];
    }
}