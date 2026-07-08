<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaChecklist extends Model
{
    use HasFactory;

    protected $table = 'tarea_checklists';

    protected $fillable = [
        'tarea_id',
        'item_descripcion',
        'esta_completado',
        'orden',
    ];

    protected $casts = [
        'esta_completado' => 'boolean',
    ];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'tarea_id');
    }
}
