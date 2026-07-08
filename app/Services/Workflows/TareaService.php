<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Exceptions\Workflows\UncompletedChecklistException;
use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\TareaChecklist;
use App\Models\Workflows\Workflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TareaService
{
    protected Tarea $tarea;

    protected ?UploadedFile $file = null;

    public function __construct(Tarea $tarea, ?UploadedFile $file = null)
    {
        $this->tarea = $tarea;
        $this->file = $file;
    }

    public function store(array $data, User $user): Tarea
    {
        return DB::transaction(function () use ($data, $user) {
            Workflow::findOrFail($data['workflow_id']);

            $sanitizedDesc = isset($data['descripcion'])
                ? (strip_tags($data['descripcion']) ?: null)
                : null;

            $tarea = Tarea::create([
                'workflow_id' => $data['workflow_id'],
                'nombre' => $data['nombre'],
                'descripcion' => $sanitizedDesc,
                'fecha_limite' => $data['fecha_limite'] ?? null,
                'estado' => $data['estado'] ?? 'pendiente',
            ]);

            $tarea->propietarios()->attach($user->id);
            $tarea->responsables()->attach($user->id);

            if (!empty($data['checklists'])) {
                $checklistItems = [];
                foreach ($data['checklists'] as $index => $item) {
                    $checklistItems[] = new TareaChecklist([
                        'item_descripcion' => $item['item_descripcion'] ?? $item,
                        'esta_completado' => $item['esta_completado'] ?? false,
                        'orden' => $item['orden'] ?? $index,
                    ]);
                }
                $tarea->checklists()->saveMany($checklistItems);
            }

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function update(Tarea $tarea, array $data): Tarea
    {
        return DB::transaction(function () use ($tarea, $data) {
            $updateData = [];

            if (isset($data['nombre'])) {
                $updateData['nombre'] = $data['nombre'];
            }

            if (isset($data['descripcion'])) {
                $clean = strip_tags($data['descripcion']);
                if ($clean !== '') {
                    $updateData['descripcion'] = $clean;
                }
            }

            if (isset($data['fecha_limite'])) {
                $updateData['fecha_limite'] = $data['fecha_limite'];
            }

            if (isset($data['estado'])) {
                if ($data['estado'] === 'completada') {
                    $pendingCount = $tarea->checklists()->where('esta_completado', false)->count();
                    if ($pendingCount > 0) {
                        throw new UncompletedChecklistException($pendingCount);
                    }
                    $updateData['completada_at'] = now();
                }
                $updateData['estado'] = $data['estado'];
            }

            if (!empty($updateData)) {
                $tarea->update($updateData);
            }

            if (isset($data['propietarios'])) {
                $tarea->propietarios()->sync($data['propietarios']);
            }

            if (isset($data['responsables'])) {
                $tarea->responsables()->sync($data['responsables']);
            }

            if (isset($data['checklists'])) {
                $incomingIds = collect($data['checklists'])->pluck('id')->filter();
                $tarea->checklists()->whereNotIn('id', $incomingIds)->delete();

                foreach ($data['checklists'] as $index => $item) {
                    if (!empty($item['id'])) {
                        TareaChecklist::where('id', $item['id'])
                            ->where('tarea_id', $tarea->id)
                            ->update([
                                'item_descripcion' => $item['item_descripcion'] ?? $item,
                                'esta_completado' => $item['esta_completado'] ?? false,
                                'orden' => $item['orden'] ?? $index,
                            ]);
                    } else {
                        $tarea->checklists()->create([
                            'item_descripcion' => $item['item_descripcion'] ?? $item,
                            'esta_completado' => $item['esta_completado'] ?? false,
                            'orden' => $item['orden'] ?? $index,
                        ]);
                    }
                }
            }

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function addPropietario(Tarea $tarea, User $user): void
    {
        if (!$tarea->propietarios()->where('user_id', $user->id)->exists()) {
            $tarea->propietarios()->attach($user->id);
        }
    }

    public function addResponsable(Tarea $tarea, User $user): void
    {
        if (!$tarea->responsables()->where('user_id', $user->id)->exists()) {
            $tarea->responsables()->attach($user->id);
        }
    }

    public function removePropietario(Tarea $tarea, User $user): void
    {
        $tarea->propietarios()->detach($user->id);
    }

    public function removeResponsable(Tarea $tarea, User $user): void
    {
        $tarea->responsables()->detach($user->id);
    }

    public function completar(Tarea $tarea): Tarea
    {
        return DB::transaction(function () use ($tarea) {
            $tarea = $tarea->fresh('checklists');

            $pendingCount = $tarea->checklists->where('esta_completado', false)->count();

            if ($pendingCount > 0) {
                throw new UncompletedChecklistException($pendingCount);
            }

            $tarea->update([
                'estado' => 'completada',
                'completada_at' => now(),
            ]);

            return $tarea->load(['propietarios', 'responsables', 'checklists', 'workflow']);
        });
    }

    public function reordenarChecklist(Tarea $tarea, array $orden): void
    {
        foreach ($orden as $index => $checklistId) {
            TareaChecklist::where('tarea_id', $tarea->id)
                ->where('id', $checklistId)
                ->update(['orden' => $index]);
        }
    }
}
