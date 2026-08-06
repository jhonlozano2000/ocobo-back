<?php

declare(strict_types=1);

namespace App\Exceptions\Workflows;

class StateTransitionException extends WorkflowException
{
    public function __construct(string $message = 'Transición de estado no válida', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
