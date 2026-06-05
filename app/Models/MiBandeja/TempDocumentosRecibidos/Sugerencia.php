<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sugerencia de cambio en un documento colaborativo (modo revisión/track changes).
 *
 * Representa una propuesta de modificación al contenido del documento
 * que puede ser aceptada o rechazada por usuarios con permisos.
 *
 * @property int $id
 * @property int $documento_id ID del documento
 * @property int $user_id ID del usuario que creó la sugerencia
 * @property int|null $parent_id ID de la sugerencia padre (para agrupar relacionadas)
 * @property string $tipo Tipo: insercion|eliminacion|reemplazo|formato
 * @property string|null $texto_original Texto original a modificar
 * @property string|null $texto_sugerido Texto propuesto como reemplazo
 * @property array|null $posicion Posición en el documento {from, to}
 * @property string|null $justificación Razón del cambio propuesto
 * @property string $estado Estado: pendiente|aceptada|rechazada
 * @property int|null $resuelto_por Usuario que resolvió la sugerencia
 * @property \Carbon\Carbon|null $fecha_resolucion Fecha de resolución
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Documento $documento
 * @property-read User $usuario
 * @property-read User|null $resueltoPor
 * @property-read Sugerencia|null $padre
 * @property-read HasMany<Sugerencia> $respuestas
 */
class Sugerencia extends Model
{
    protected $table = 'mi_bandeja_temp_reci_sugerencias';

    protected $fillable = [
        'documento_id',
        'user_id',
        'parent_id',
        'tipo',
        'texto_original',
        'texto_sugerido',
        'posicion',
        'justificacion',
        'estado',
        'resuelto_por',
        'fecha_resolucion',
    ];

    protected $casts = [
        'posicion' => 'array',
        'fecha_resolucion' => 'datetime',
    ];

    public const TIPOS = [
        'insercion' => 'Inserción',
        'eliminacion' => 'Eliminación',
        'reemplazo' => 'Reemplazo',
        'formato' => 'Cambio de formato',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'aceptada' => 'Aceptada',
        'rechazada' => 'Rechazada',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Sugerencia::class, 'parent_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(Sugerencia::class, 'parent_id');
    }

    public function aceptar(User $usuario): void
    {
        $this->estado = 'aceptada';
        $this->resuelto_por = $usuario->id;
        $this->fecha_resolucion = now();
        $this->save();
    }

    public function rechazar(User $usuario): void
    {
        $this->estado = 'rechazada';
        $this->resuelto_por = $usuario->id;
        $this->fecha_resolucion = now();
        $this->save();
    }

    public function aplicarAlContenido(array &$contenido): array
    {
        if ($this->estado !== 'aceptada') {
            return $contenido;
        }

        if (!$this->posicion || !isset($this->posicion['from']) || !isset($this->posicion['to'])) {
            return $contenido;
        }

        return match ($this->tipo) {
            'insercion' => $this->aplicarInsercion($contenido),
            'eliminacion' => $this->aplicarEliminacion($contenido),
            'reemplazo' => $this->aplicarReemplazo($contenido),
            'formato' => $this->aplicarFormato($contenido),
            default => $contenido,
        };
    }

    private function aplicarInsercion(array $contenido): array
    {
        $from = $this->posicion['from'];
        $texto = $this->texto_sugerido ?? '';

        $nuevoContenido = $this->clonarContenido($contenido);
        $this->insertarTextoEnPosicion($nuevoContenido, $from, $texto);

        return $nuevoContenido;
    }

    private function aplicarEliminacion(array $contenido): array
    {
        $from = $this->posicion['from'];
        $to = $this->posicion['to'];

        $nuevoContenido = $this->clonarContenido($contenido);
        $this->eliminarTextoEnRango($nuevoContenido, $from, $to);

        return $nuevoContenido;
    }

    private function aplicarReemplazo(array $contenido): array
    {
        $from = $this->posicion['from'];
        $to = $this->posicion['to'];
        $texto = $this->texto_sugerido ?? '';

        $nuevoContenido = $this->clonarContenido($contenido);
        $this->eliminarTextoEnRango($nuevoContenido, $from, $to);
        $this->insertarTextoEnPosicion($nuevoContenido, $from, $texto);

        return $nuevoContenido;
    }

    private function aplicarFormato(array $contenido): array
    {
        return $contenido;
    }

    private function clonarContenido(array $contenido): array
    {
        return json_decode(json_encode($contenido), true);
    }

    private function insertarTextoEnPosicion(array &$contenido, int $posicion, string $texto): void
    {
        if (!isset($contenido['content'])) return;

        $offset = 0;
        foreach ($contenido['content'] as &$bloque) {
            if ($bloque['type'] === 'paragraph' && isset($bloque['content'])) {
                foreach ($bloque['content'] as &$inline) {
                    if ($inline['type'] === 'text') {
                        $longitud = strlen($inline['text']);
                        if ($posicion >= $offset && $posicion <= $offset + $longitud) {
                            $posRelativa = $posicion - $offset;
                            $inline['text'] = substr($inline['text'], 0, $posRelativa) . $texto . substr($inline['text'], $posRelativa);
                            return;
                        }
                        $offset += $longitud;
                    }
                }
            }
        }
    }

    private function eliminarTextoEnRango(array &$contenido, int $from, int $to): void
    {
        if (!isset($contenido['content'])) return;

        $offset = 0;
        foreach ($contenido['content'] as &$bloque) {
            if ($bloque['type'] === 'paragraph' && isset($bloque['content'])) {
                foreach ($bloque['content'] as &$inline) {
                    if ($inline['type'] === 'text') {
                        $longitud = strlen($inline['text']);
                        $inicioBloque = $offset;
                        $finBloque = $offset + $longitud;

                        if ($from < $finBloque && $to > $inicioBloque) {
                            $inicioRelativo = max(0, $from - $inicioBloque);
                            $finRelativo = min($longitud, $to - $inicioBloque);
                            $inline['text'] = substr($inline['text'], 0, $inicioRelativo) . substr($inline['text'], $finRelativo);
                        }

                        $offset += $longitud;
                    }
                }
            }
        }
    }
}
