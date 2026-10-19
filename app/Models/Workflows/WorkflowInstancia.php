<?php

namespace App\Models\Workflows;

use App\Models\User;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsOptimizedView;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowInstancia extends Model
{
    use HasFactory;

    /** M19-30: orígenes permitidos para una instancia. */
    public const ORIGENES = ['radicado_recibido', 'pqrs'];

    protected $table = 'workflow_instancias';

    protected $fillable = [
        'workflow_id',
        'nodo_actual_id',
        'estado',
        'estado_vencimiento',
        'usuario_ejecuta_id',
        'origen_tipo',
        'origen_id',
        'radicado_id',
        'fecha_inicio',
        'fecha_fin',
        'fecha_limite_estimada',
        'datos_contexto',
    ];

    protected $casts = [
        'datos_contexto' => 'array',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'fecha_limite_estimada' => 'datetime',
    ];

    /**
     * M19-52: instancias que el usuario puede ver. El Administrador y el creador del workflow ven
     * todas; los demás, las que iniciaron, las que tienen una tarea suya (plantilla del nodo o
     * tarea generada, como responsable o propietario) y las de un radicado o PQRS que pueden ver.
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Administrador')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('usuario_ejecuta_id', $user->id)
                ->orWhereIn('workflow_id', Workflow::where('creador_user_id', $user->id)->select('id'))
                ->orWhereIn('id', fn ($s) => $s->select('instancia_id')->from('workflow_nodos_instancia')
                    ->whereIn('nodo_id', fn ($t) => $t->select('nodo_id')->from('work_flow_tareas')->where('responsable_usuario_id', $user->id)))
                ->orWhereIn('id', fn ($s) => $s->select('instancia_id')->from('workflow_tareas')->whereNotNull('instancia_id')
                    ->where(fn ($t) => $t->whereIn('id', fn ($r) => $r->select('tarea_id')->from('tarea_responsables')->where('user_id', $user->id))
                        ->orWhereIn('id', fn ($r) => $r->select('tarea_id')->from('tarea_propietarios')->where('user_id', $user->id))))
                ->orWhereIn('radicado_id', VentanillaRadicaReci::conPermisoJerarquico($user)->select('id'))
                ->orWhereIn('radicado_id', VentanillaPqrsOptimizedView::conPermisoJerarquico($user)->select('ventanilla_radica_reci_id'));
        });
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function nodoActual()
    {
        return $this->belongsTo(WorkflowNodo::class, 'nodo_actual_id');
    }

    public function usuarioEjecuta()
    {
        return $this->belongsTo(User::class, 'usuario_ejecuta_id');
    }

    /** M19-30: radicado recibido de origen (el de la PQRS si el origen es una PQRS). */
    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    public function nodosInstancia()
    {
        return $this->hasMany(WorkflowNodoInstancia::class, 'instancia_id');
    }

    /** Tareas (sistema canónico, M19-17) creadas al pasar la instancia por cada nodo. */
    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'instancia_id');
    }

    public function archivos()
    {
        return $this->morphMany(WorkFlowArchivo::class, 'archivable');
    }
}
