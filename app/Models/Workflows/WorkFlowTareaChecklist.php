<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkFlowTareaChecklist extends Model
{
    use HasFactory;

    protected $table = 'work_flow_tarea_checklists';

    protected $fillable = [
        'work_flow_tarea_id',
        'item_descripcion',
        'esta_completado',
        'orden',
    ];

    protected $casts = [
        'esta_completado' => 'boolean',
    ];

    public function workFlowTarea(): BelongsTo
    {
        return $this->belongsTo(WorkFlowTarea::class, 'work_flow_tarea_id');
    }
}
