<?php

namespace App\Traits;

use App\Models\UsersActivityLog;
use Illuminate\Support\Facades\Auth;

trait AuditViewTrait
{
    /**
     * Registra un evento de consulta (View) en el log de auditoría.
     * Cumplimiento ISO 27001 - Trazabilidad de acceso.
     *
     * @param mixed $model El objeto consultado (Radicado, Expediente, etc)
     * @param string $descripcion Contexto de la consulta
     * @return void
     */
    public function auditView($model, string $descripcion = 'Consulta de información')
    {
        UsersActivityLog::log([
            'module' => class_basename($model),
            'action' => 'view',
            'description' => $descripcion,
            'entity_id' => $model->getKey(),
            'entity_type' => get_class($model),
            'new_values' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }
}
