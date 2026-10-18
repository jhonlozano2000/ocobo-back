<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowSetting extends Model
{
    /**
     * M19-26: cómo se reparten las tareas de nodo que el diseño no fija a una persona.
     * - manual: responsable del nodo o, si no hay, quien ejecuta la instancia.
     * - fila: rotación entre los miembros activos del equipo.
     * - autoasignar: la tarea llega a todo el equipo y se la queda el primero que la inicia.
     * 'automatizacion' existe en el enum de la tabla pero no tiene reglas definidas: no se acepta.
     */
    public const ESTRATEGIAS = ['manual', 'fila', 'autoasignar'];

    public const ESTRATEGIAS_CON_EQUIPO = ['fila', 'autoasignar'];

    protected $table = 'workflow_settings';

    protected $fillable = [
        'workflow_id',
        'estrategia_asignacion',
        'configuracion_asignacion_json',
        'alertas_kpi_json',
        'opciones_adicionales_json',
    ];

    protected $casts = [
        'configuracion_asignacion_json' => 'array',
        'alertas_kpi_json' => 'array',
        'opciones_adicionales_json' => 'array',
    ];

    /** @return list<int> */
    public function equipo(): array
    {
        return array_map('intval', $this->configuracion_asignacion_json['equipo'] ?? []);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}
