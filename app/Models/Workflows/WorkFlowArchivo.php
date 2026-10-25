<?php

namespace App\Models\Workflows;

use App\Models\Concerns\RegistraMetricaArchivo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkFlowArchivo extends Model
{
    use HasFactory, RegistraMetricaArchivo;

    protected $table = 'work_flow_archivos';

    /** M19-52: los adjuntos del diseño son visibles; los de una instancia, solo si se ve la instancia. */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('archivable_type', '!=', WorkflowInstancia::class)
            ->orWhereIn('archivable_id', WorkflowInstancia::visiblesPara($user)->select('id')));
    }

    protected $fillable = [
        'workflow_id',
        'archivable_type',
        'archivable_id',
        'nombre_original',
        'nombre_almacenado',
        'ruta_almacenada',
        'mime_type',
        'peso_bytes',
        'hash_sha256',
        'disk',
        'categoria',
        'uploaded_by',
    ];

    protected $casts = [
        'peso_bytes' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function archivable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Métricas de archivos: archivo de un workflow o tarea.
     */
    public function datosMetricaArchivo(): array
    {
        return [
            'modulo' => 'workflows',
            'submodulo' => 'workflow_archivos',
            'ruta' => $this->ruta_almacenada,
            'bytes' => $this->peso_bytes,
            'extension' => null,
            'mime' => $this->mime_type,
            'user_id' => $this->uploaded_by,
        ];
    }
}
