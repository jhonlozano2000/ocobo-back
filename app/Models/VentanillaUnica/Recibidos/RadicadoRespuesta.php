<?php

namespace App\Models\VentanillaUnica\Recibidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class RadicadoRespuesta extends Model
{
    protected $table = 'ventanilla_radica_reci_respuestas';

    protected $fillable = [
        'radicado_id',
        'titulo',
        'contenido',
        'contenido_json',
        'version',
        'version_actual',
        'estado',
        'user_editando_id',
        'fecha_inicio_edicion',
        'lock_tiempo',
        'user_crea_id',
        'user_actualiza_id',
    ];

    protected $casts = [
        'contenido_json' => 'array',
        'version' => 'integer',
        'version_actual' => 'integer',
        'fecha_inicio_edicion' => 'datetime',
    ];

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(RadicadoRespuestaVersion::class, 'respuesta_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(RadicadoRespuestaParticipante::class, 'respuesta_id');
    }

    public function usuarioCrea(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_crea_id');
    }

    public function usuarioEditando(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_editando_id');
    }

    public function usuarioActualiza(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_actualiza_id');
    }

    public function estaBloqueada(): bool
    {
        if (!$this->user_editando_id) {
            return false;
        }
        
        $TiempoTranscurrido = now()->diffInSeconds($this->fecha_inicio_edicion);
        return $TiempoTranscurrido < $this->lock_tiempo;
    }

    public function puedeEditar(): bool
    {
        if ($this->estado === 'finalizado' || $this->estado === 'enviado') {
            return false;
        }
        
        $userId = Auth::id();
        
        if ($this->user_editando_id && $this->user_editando_id !== $userId) {
            if ($this->estaBloqueada()) {
                return false;
            }
        }
        
        return true;
    }

    public function adquirirLock(): bool
    {
        if (!$this->puedeEditar()) {
            return false;
        }
        
        $this->update([
            'user_editando_id' => Auth::id(),
            'fecha_inicio_edicion' => now(),
            'estado' => 'en_edicion',
        ]);
        
        return true;
    }

    public function liberarLock(): void
    {
        $this->update([
            'user_editando_id' => null,
            'fecha_inicio_edicion' => null,
            'estado' => 'borrador',
        ]);
    }

    public function guardarVersion(string $resumen = null): RadicadoRespuestaVersion
    {
        return RadicadoRespuestaVersion::create([
            'respuesta_id' => $this->id,
            'version' => $this->version_actual,
            'contenido' => $this->contenido,
            'contenido_json' => $this->contenido_json,
            'user_id' => Auth::id(),
            'cambios_resumen' => $resumen,
        ]);
    }
}