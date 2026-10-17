<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Tarea extends Model
{
    use HasFactory;

    protected $table = 'workflow_tareas';

    protected $fillable = [
        'workflow_id',
        'instancia_id',
        'nodo_id',
        'plantilla_id',
        'nombre',
        'descripcion',
        'fecha_limite',
        'estado',
        'completada_at',
    ];

    protected $casts = [
        'fecha_limite' => 'datetime',
        'completada_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    /** Instancia y nodo de los que nace la tarea (M19-17); null en tareas sueltas del módulo. */
    public function instancia(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstancia::class, 'instancia_id');
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(WorkflowNodo::class, 'nodo_id');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(WorkFlowTarea::class, 'plantilla_id');
    }

    public function propietarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tarea_propietarios', 'tarea_id', 'user_id');
    }

    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tarea_responsables', 'tarea_id', 'user_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TareaChecklist::class, 'tarea_id')->orderBy('orden');
    }

    /** Evidencia adjunta por propietarios y responsables (M19-35). */
    public function archivos(): MorphMany
    {
        return $this->morphMany(WorkFlowArchivo::class, 'archivable');
    }
}
