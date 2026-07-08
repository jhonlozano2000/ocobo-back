<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkFlowTarea extends Model
{
    use HasFactory;

    protected $table = 'work_flow_tareas';

    protected $fillable = [
        'nodo_id',
        'instancia_id',
        'responsable_usuario_id',
        'titulo',
        'descripcion',
        'instrucciones',
        'tiempo_limite_horas',
        'fecha_limite',
        'estado',
        'adjuntos_permitidos',
        'orden',
        'resultado_json',
    ];

    protected $casts = [
        'fecha_limite' => 'datetime',
        'adjuntos_permitidos' => 'boolean',
        'resultado_json' => 'array',
    ];

    public function nodo()
    {
        return $this->belongsTo(WorkflowNodo::class, 'nodo_id');
    }

    public function instancia()
    {
        return $this->belongsTo(WorkflowInstancia::class, 'instancia_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_usuario_id');
    }

    public function archivos()
    {
        return $this->morphMany(WorkFlowArchivo::class, 'archivable');
    }
}
