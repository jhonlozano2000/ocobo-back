<?php

declare(strict_types=1);

namespace App\Exceptions\Workflows;

use Exception;

abstract class WorkflowException extends Exception
{
    public function __construct(string $message = '', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    abstract public function getStatusCode(): int;
}
