<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowNodoInstancia extends Model
{
    use HasFactory;

    protected $table = 'workflow_nodos_instancia';

    protected $fillable = [
        'instancia_id',
        'nodo_id',
        'estado',
        'fecha_ejecucion',
        'resultado_json',
    ];

    protected $casts = [
        'resultado_json' => 'array',
        'fecha_ejecucion' => 'datetime',
    ];

    public function instancia()
    {
        return $this->belongsTo(WorkflowInstancia::class, 'instancia_id');
    }

    public function nodo()
    {
        return $this->belongsTo(WorkflowNodo::class, 'nodo_id');
    }

    public function tareas()
    {
        return $this->hasManyThrough(
            WorkFlowTarea::class,
            WorkflowNodo::class,
            'id',
            'nodo_id',
            'nodo_id',
            'id'
        );
    }
}
