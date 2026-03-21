<?php

namespace App\Traits;

use Spatie\Activitylog\Models\Activity;
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
        activity()
            ->performedOn($model)
            ->causedBy(Auth::user())
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'action' => 'view'
            ])
            ->log($descripcion);
    }
}
