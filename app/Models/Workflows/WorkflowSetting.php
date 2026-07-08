<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowSetting extends Model
{
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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}
