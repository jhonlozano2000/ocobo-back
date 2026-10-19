<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use App\Services\OfiArchivo\ExpedienteEventosService;
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
            // M08-07: un folio nunca se reutiliza. Se cuentan también los documentos inactivos
            // (el índice único uk_expediente_folio los incluye) y se bloquea el expediente
            // para que dos incorporaciones simultáneas no calculen el mismo número.
            OfiArchivoExpediente::whereKey($doc->expediente_id)->lockForUpdate()->first();

            $ultimoFolio = self::where('expediente_id', $doc->expediente_id)
                ->max('numero_folio') ?? 0;
            $doc->numero_folio = $ultimoFolio + 1;

            if (! $doc->fecha_incorporacion) {
                $doc->fecha_incorporacion = now();
            }
        });

        static::created(function ($doc) {
            $doc->expediente->increment('total_folios_elec');

            app(ExpedienteEventosService::class)->registrar($doc->expediente_id, 'incorporacion', array_filter([
                'documento_id' => $doc->id,
                'numero_folio' => $doc->numero_folio,
                'tipo_documental' => $doc->tipo_documental,
                'origen' => $doc->documentable_type ? class_basename($doc->documentable_type).'#'.$doc->documentable_id : null,
                'nombre_original' => $doc->nombre_original,
                'hash_sha256' => $doc->hash_sha256,
            ], fn ($v) => $v !== null));
        });

        // No hay boot deleted: delete() override maneja el decremento
    }

    /**
     * Borrado lógico en vez de DELETE físico (ISO 27001 A.12.4.1)
     */
    public function delete()
    {
        $this->expediente->decrement('total_folios_elec');
        $this->update(['activo' => false]);

        app(ExpedienteEventosService::class)->registrar($this->expediente_id, 'retiro', [
            'documento_id' => $this->id,
            'numero_folio' => $this->numero_folio,
        ]);

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
