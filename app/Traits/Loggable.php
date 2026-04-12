<?php

namespace App\Traits;

use App\Models\UsersActivityLog;

trait Loggable
{
    protected static function bootLoggable(): void
    {
        // Hooks automáticos para modelos Eloquent
        static::created(function ($model) {
            self::logActivity('create', $model, 'created');
        });

        static::updated(function ($model) {
            if ($model->wasChanged()) {
                self::logActivity('update', $model, 'updated', $model->getOriginal());
            }
        });

        static::deleted(function ($model) {
            self::logActivity('delete', $model, 'deleted');
        });
    }

    protected static function logActivity(string $action, $model, string $event, ?array $oldValues = null): void
    {
        $module = self::getModuleName();
        $isCritical = self::isCriticalAction($action);

        $entityType = get_class($model);
        $entityId = $model->getKey();

        // Valores nuevos (solo para create/update)
        $newValues = $action !== 'delete' ? $model->toArray() : null;

        // Valores old (solo para update)
        $old = $oldValues;

        UsersActivityLog::log([
            'module'       => $module,
            'action'       => $action,
            'description'  => self::getLogDescription($action, $model),
            'entity_id'    => $entityId,
            'entity_type'  => $entityType,
            'old_values'   => $old,
            'new_values'   => $newValues,
            'is_critical'  => $isCritical,
        ]);
    }

    protected static function getModuleName(): string
    {
        $class = static::class;
        $module = match (true) {
            str_contains($class, 'Ventanilla') => 'Ventanilla Única',
            str_contains($class, 'ControlAcceso') => 'Control de Acceso',
            str_contains($class, 'Config') => 'Configuración',
            str_contains($class, 'Calidad') => 'Calidad',
            str_contains($class, 'Archivo') => 'Gestión Archivo',
            str_contains($class, 'Gestion') => 'Gestión',
            str_contains($class, 'Clasifica') => 'Clasificación Documental',
            default => 'General'
        };
        return $module;
    }

    protected static function isCriticalAction(string $action): bool
    {
        return in_array($action, ['delete', 'export', 'download', 'change_status']);
    }

    protected static function getLogDescription(string $action, $model): string
    {
        $name = $model->name ?? $model->nombres ?? $model->num_radicado ?? $model->titulo ?? 'Elemento';
        $actionLabel = match ($action) {
            'create' => 'creó',
            'update' => 'actualizó',
            'delete' => 'eliminó',
            default => $action
        };

        return "{$actionLabel} {$name}";
    }
}