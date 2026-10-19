<?php

namespace App\Models\Workflows;

use App\Models\Workflows\Concerns\TextoPlano;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowNodo extends Model
{
    use HasFactory, TextoPlano;

    protected $table = 'workflow_nodos';

    protected array $textoPlano = ['titulo', 'descripcion'];

    protected $fillable = [
        'workflow_id',
        'tipo',
        'titulo',
        'descripcion',
        'posicion_x',
        'posicion_y',
        'configuracion_json',
        'responsable_usuario_id',
        'tiempo_limite_horas',
        'tiempo_limite_unidad',
        'adjuntos_permitidos',
        'orden_ejecucion',
    ];

    protected $casts = [
        'configuracion_json' => 'array',
        'posicion_x' => 'float',
        'posicion_y' => 'float',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function conexionesOrigen()
    {
        return $this->hasMany(WorkflowConexion::class, 'nodo_origen_id');
    }

    public function conexionesDestino()
    {
        return $this->hasMany(WorkflowConexion::class, 'nodo_destino_id');
    }

    public function instancias()
    {
        return $this->hasMany(WorkflowNodoInstancia::class, 'nodo_id');
    }

    public function tareas()
    {
        return $this->hasMany(WorkFlowTarea::class, 'nodo_id');
    }
}
