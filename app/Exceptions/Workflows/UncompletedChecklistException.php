<?php

declare(strict_types=1);

namespace App\Exceptions\Workflows;

use Exception;

class UncompletedChecklistException extends Exception
{
    public function __construct(int $pendingCount = 0)
    {
        $message = $pendingCount > 0
            ? "No se puede completar la tarea: {$pendingCount} ítem(es) de la lista de verificación están pendientes."
            : 'No se puede completar la tarea: hay ítems pendientes en la lista de verificación.';

        parent::__construct($message, 422);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['checklists' => [$this->getMessage()]],
        ], 422);
    }
}
