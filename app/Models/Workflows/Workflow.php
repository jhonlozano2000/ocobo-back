<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Workflow extends Model
{
    protected $table = 'workflows';

    protected $fillable = [
        'uuid',
        'nombre',
        'descripcion',
        'tiempo_finalizacion_horas',
        'creador_user_id',
        'administrador_user_id',
        'estado',
    ];

    protected $casts = [
        'tiempo_finalizacion_horas' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Workflow $workflow): void {
            if (empty($workflow->uuid)) {
                $workflow->uuid = (string) Str::uuid();
            }
        });
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_user_id');
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrador_user_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(WorkflowSetting::class, 'workflow_id');
    }

    public function nodos(): HasMany
    {
        return $this->hasMany(WorkflowNodo::class, 'workflow_id');
    }

    public function conexiones(): HasMany
    {
        return $this->hasMany(WorkflowConexion::class, 'workflow_id');
    }

    public function instancias(): HasMany
    {
        return $this->hasMany(WorkflowInstancia::class, 'workflow_id');
    }

    public function nodoInicio(): mixed
    {
        return $this->nodos()->where('tipo', 'inicio')->first();
    }

    public function tareas(): HasManyThrough
    {
        return $this->hasManyThrough(WorkFlowTarea::class, WorkflowNodo::class, 'workflow_id', 'nodo_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(WorkFlowArchivo::class, 'workflow_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(WorkflowAudit::class, 'workflow_id');
    }
}
