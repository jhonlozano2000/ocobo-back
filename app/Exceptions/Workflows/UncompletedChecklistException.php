<?php

declare(strict_types=1);

namespace App\Exceptions\Workflows;

use Illuminate\Http\JsonResponse;

class UncompletedChecklistException extends WorkflowException
{
    public function __construct(int $pendingCount = 0)
    {
        $message = $pendingCount > 0
            ? "No se puede completar la tarea: {$pendingCount} ítem(es) de la lista de verificación están pendientes."
            : 'No se puede completar la tarea: hay ítems pendientes en la lista de verificación.';

        parent::__construct($message, 422);
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['checklists' => [$this->getMessage()]],
        ], 422);
    }
}
