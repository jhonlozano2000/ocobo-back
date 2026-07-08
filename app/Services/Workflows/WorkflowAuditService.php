<?php

namespace App\Services\Workflows;

use App\Models\Workflows\WorkflowAudit;

/**
 * Servicio de auditoría para Workflows (ISO 27001 A.12.4.1).
 *
 * Trazabilidad inmutable: solo INSERT, sin DELETE ni UPDATE.
 * Registra acciones críticas del módulo Workflows con datos forenses.
 */
class WorkflowAuditService
{
    public function registrar(string $action, ?int $workflowId, ?int $instanciaId, array $payload = []): WorkflowAudit
    {
        return WorkflowAudit::create([
            'workflow_id' => $workflowId,
            'instancia_id' => $instanciaId,
            'user_id' => auth()->id(),
            'action' => $action,
            'payload_json' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
