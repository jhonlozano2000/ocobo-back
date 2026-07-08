<?php

declare(strict_types=1);

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarea extends Model
{
    use HasFactory;

    protected $table = 'workflow_tareas';

    protected $fillable = [
        'workflow_id',
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
}
