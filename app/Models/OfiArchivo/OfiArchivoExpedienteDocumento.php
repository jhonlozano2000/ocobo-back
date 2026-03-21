<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfiArchivoExpedienteDocumento extends Model
{
    use HasFactory;

    protected $table = 'ofi_archivo_expedientes_documentos';

    protected $fillable = [
        'expediente_id',
        'numero_folio',
        'documentable_id',
        'documentable_type',
        'detalle',
        'fecha_incorporacion',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_incorporacion' => 'datetime',
        'numero_folio' => 'integer',
    ];

    /**
     * Lógica de Foliación Automática (ISO 27001 - Integridad)
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de crear el registro, calculamos el siguiente folio
        static::creating(function ($doc) {
            $ultimoFolio = self::where('expediente_id', $doc->expediente_id)->max('numero_folio') ?? 0;
            $doc->numero_folio = $ultimoFolio + 1;
            
            if (!$doc->fecha_incorporacion) {
                $doc->fecha_incorporacion = now();
            }
        });

        // Después de crear, actualizamos el contador en el expediente padre
        static::created(function ($doc) {
            $doc->expediente->increment('total_folios_elec');
        });

        // Si se elimina un documento, decrementamos el contador
        static::deleted(function ($doc) {
            $doc->expediente->decrement('total_folios_elec');
        });
    }

    /**
     * Relación con el expediente padre.
     */
    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    /**
     * Relación Polimórfica (RadicadoRecibido, RadicadoEnviado, etc).
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Usuario que incorporó el documento al expediente.
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }
}
