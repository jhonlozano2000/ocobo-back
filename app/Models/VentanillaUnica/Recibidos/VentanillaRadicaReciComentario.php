<?php

namespace App\Models\VentanillaUnica\Recibidos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class VentanillaRadicaReciComentario extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_comentarios';

    protected $fillable = [
        'radica_reci_id',
        'user_id',
        'contenido',
        'parent_id',
        'resuelto',
        'resuelto_por',
        'fecha_resolucion',
        'etiquetas',
        'es_nota_interna',
    ];

    protected $casts = [
        'resuelto' => 'boolean',
        'es_nota_interna' => 'boolean',
        'fecha_resolucion' => 'datetime',
        'etiquetas' => 'array',
    ];

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioResolucion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReciComentario::class, 'parent_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(VentanillaRadicaReciComentario::class, 'parent_id');
    }

    public function tieneRespuestas(): bool
    {
        return $this->respuestas()->count() > 0;
    }

    public function resolver(int $userId): bool
    {
        $this->update([
            'resuelto' => true,
            'resuelto_por' => $userId,
            'fecha_resolucion' => now(),
        ]);
        return true;
    }

    public function getInfoUsuario(): ?array
    {
        if (!$this->usuario) {
            return null;
        }
        return $this->usuario->getInfoUsuario();
    }

    public function getInfo(): array
    {
        return [
            'id' => $this->id,
            'contenido' => $this->contenido,
            'fecha' => $this->created_at,
            'resuelto' => $this->resuelto,
            'fecha_resolucion' => $this->fecha_resolucion,
            'es_nota_interna' => $this->es_nota_interna,
            'usuario' => $this->getInfoUsuario(),
            'respuestas' => $this->respuestas->map(fn($r) => $r->getInfo())->toArray(),
            'tiene_respuestas' => $this->tieneRespuestas(),
        ];
    }
}
