<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfiArchivoExpedienteDocumento extends Model
{
    use HasFactory;

    protected $table = 'ofi_archivo_expedientes_documentos';

    protected $fillable = [
        'expediente_id',
        'tipo',
        'tipo_documental',
        'orden',
        'fecha_documento',
        'asunto',
        'autor',
        'formato_archivo',
        'tamano_bytes',
        'documentable_type',
        'documentable_id',
        'detalle',
        'archivo_path',
        'hash_sha256',
        'nombre_original',
        'activo',
        'fecha_incorporacion',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_incorporacion' => 'datetime',
        'numero_folio' => 'integer',
        'activo' => 'boolean',
    ];

    /**
     * Lógica de Foliación Automática (ISO 27001 - Integridad)
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($doc) {
            $ultimoFolio = self::where('expediente_id', $doc->expediente_id)
                ->where('activo', true)
                ->max('numero_folio') ?? 0;
            $doc->numero_folio = $ultimoFolio + 1;

            if (! $doc->fecha_incorporacion) {
                $doc->fecha_incorporacion = now();
            }
        });

        static::created(function ($doc) {
            $doc->expediente->increment('total_folios_elec');
        });

        static::deleted(function ($doc) {
            $doc->expediente->decrement('total_folios_elec');
        });
    }

    /**
     * Borrado lógico en vez de DELETE físico (ISO 27001 A.12.4.1)
     */
    public function delete()
    {
        $this->expediente->decrement('total_folios_elec');
        $this->update(['activo' => false]);

        return true;
    }

    /**
     * Scope: solo documentos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
