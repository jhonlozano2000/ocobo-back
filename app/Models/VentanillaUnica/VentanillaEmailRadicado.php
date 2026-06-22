<?php

namespace App\Models\VentanillaUnica;

use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para el seguimiento de correos electrónicos vinculados a radicados.
 * Almacena la información de cada correo sincronizado desde el servidor IMAP
 * y su estado de radicación.
 */
class VentanillaEmailRadicado extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_email_radicados';

    protected $fillable = [
        'imap_uid',
        'imap_folder',
        'asunto',
        'remitente_email',
        'remitente_nombre',
        'fecha_correo',
        'body_text',
        'body_html',
        'tiene_adjuntos',
        'adjuntos_info',
        'radicado_id',
        'estado',
        'error_mensaje',
        'sincronizado_en',
        'radicado_en',
        'respondido_en',
    ];

    protected $casts = [
        'adjuntos_info' => 'array',
        'fecha_correo' => 'datetime',
        'tiene_adjuntos' => 'boolean',
        'sincronizado_en' => 'datetime',
        'radicado_en' => 'datetime',
        'respondido_en' => 'datetime',
    ];

    /**
     * Relación con el radicado generado a partir de este correo.
     */
    public function radicado(): BelongsTo
    {
        return $this->belongsTo(
            VentanillaRadicaReci::class,
            'radicado_id'
        );
    }

    /**
     * Scope para filtrar correos pendientes de radicación.
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Scope para filtrar correos ya radicados.
     */
    public function scopeRadicados($query)
    {
        return $query->where('estado', 'radicado');
    }

    /**
     * Scope para filtrar correos que ya han sido respondidos.
     */
    public function scopeRespondidos($query)
    {
        return $query->where('estado', 'respondido');
    }
}
