<?php

namespace App\Traits;

use App\Models\UsersActivityLog;
use Illuminate\Support\Facades\Auth;

trait VentanillaAuditTrait
{
    /**
     * Registra eventos de auditoría para operaciones de Ventanilla Única.
     * Cumplimiento ISO 27001 - Trazabilidad de cambios.
     *
     * @param mixed $model El modelo afectado
     * @param string $action Acción: created, updated, deleted, view
     * @param string $numRadicado Número de radicado para búsqueda rápida
     * @param array $metadata Metadatos adicionales
     * @return void
     */
    public function auditVentanilla($model, string $action, string $numRadicado = '', array $metadata = [])
    {
        $descriptions = [
            'created' => "Radicado creado: {$numRadicado}",
            'updated' => "Radicado actualizado: {$numRadicado}",
            'deleted' => "Radicado eliminado: {$numRadicado}",
            'view' => "Radicado consultado: {$numRadicado}",
        ];

        UsersActivityLog::log([
            'module' => 'VentanillaUnica',
            'action' => $action,
            'description' => $descriptions[$action] ?? "Operación {$action} en {$numRadicado}",
            'entity_id' => $model->getKey(),
            'entity_type' => get_class($model),
            'new_values' => array_merge([
                'num_radicado' => $numRadicado,
            ], $metadata),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}