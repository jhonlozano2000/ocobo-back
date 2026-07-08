<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowConexion extends Model
{
    use HasFactory;

    protected $table = 'workflow_conexiones';

    protected $fillable = [
        'workflow_id',
        'nodo_origen_id',
        'nodo_destino_id',
        'etiqueta',
        'condicion_json',
    ];

    protected $casts = [
        'condicion_json' => 'array',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function nodoOrigen()
    {
        return $this->belongsTo(WorkflowNodo::class, 'nodo_origen_id');
    }

    public function nodoDestino()
    {
        return $this->belongsTo(WorkflowNodo::class, 'nodo_destino_id');
    }
}
