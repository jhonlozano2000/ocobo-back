<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowInstancia extends Model
{
    use HasFactory;

    protected $table = 'workflow_instancias';

    protected $fillable = [
        'workflow_id',
        'nodo_actual_id',
        'estado',
        'estado_vencimiento',
        'usuario_ejecuta_id',
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

    public function nodosInstancia()
    {
        return $this->hasMany(WorkflowNodoInstancia::class, 'instancia_id');
    }

    public function tareas()
    {
        return $this->hasMany(WorkFlowTarea::class, 'instancia_id');
    }

    public function archivos()
    {
        return $this->morphMany(WorkFlowArchivo::class, 'archivable');
    }
}
