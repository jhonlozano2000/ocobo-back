<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use SoftDeletes;

    protected $table = 'workflows';

    /**
     * M19-22: ciclo de vida del flujo. Un activo se desactiva antes de volver a borrador;
     * archivado solo se recupera como inactivo.
     */
    public const TRANSICIONES_ESTADO = [
        'borrador' => ['activo', 'archivado'],
        'activo' => ['inactivo', 'archivado'],
        'inactivo' => ['activo', 'borrador', 'archivado'],
        'archivado' => ['inactivo'],
    ];

    protected $fillable = [
        'uuid',
        'nombre',
        'descripcion',
        'tiempo_finalizacion_horas',
        'tiempo_finalizacion_unidad',
        'creador_user_id',
        'administrador_user_id',
        'estado',
        'anotaciones',
    ];

    protected $casts = [
        'tiempo_finalizacion_horas' => 'integer',
        'anotaciones' => 'array',
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

    /**
     * Limits consultation to actual membership; participation never grants design permissions.
     */
    public function scopeParaParticipante(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('creador_user_id', $user->id)
                ->orWhere('administrador_user_id', $user->id)
                ->orWhereHas('settings', fn ($s) => $s->where(function ($team) use ($user) {
                    $team->whereJsonContains('configuracion_asignacion_json->equipo', $user->id)
                        ->orWhereJsonContains('configuracion_asignacion_json->equipo', (string) $user->id);
                }))
                ->orWhereHas('nodos', fn ($n) => $n->where('responsable_usuario_id', $user->id))
                ->orWhereHas('tareas', fn ($t) => $t->where('work_flow_tareas.responsable_usuario_id', $user->id))
                ->orWhereHas('instancias', fn ($i) => $i->where('usuario_ejecuta_id', $user->id))
                ->orWhereHas('tareasModulo', fn ($t) => $t->where(function ($assigned) use ($user) {
                    $assigned->whereHas('responsables', fn ($r) => $r->where('users.id', $user->id))
                        ->orWhereHas('propietarios', fn ($p) => $p->where('users.id', $user->id));
                }));
        });
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

    public function tareasModulo(): HasMany
    {
        return $this->hasMany(Tarea::class, 'workflow_id');
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
