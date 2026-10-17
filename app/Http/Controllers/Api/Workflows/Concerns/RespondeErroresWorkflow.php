<?php

namespace App\Http\Controllers\Api\Workflows\Concerns;

use App\Exceptions\Workflows\UncompletedChecklistException;
use App\Exceptions\Workflows\WorkflowException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * M19-06: los catch genéricos del módulo devolvían $e->getMessage() con 400 (o 404), y errorResponse
 * solo oculta el detalle desde 500 → SQL y rutas internas llegaban al cliente.
 * Las excepciones de dominio conservan su mensaje y código; el resto es 500 (detalle solo en el log).
 */
trait RespondeErroresWorkflow
{
    protected function errorDesdeExcepcion(string $message, Throwable $e): JsonResponse
    {
        if ($e instanceof AuthorizationException || $e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
            throw $e;
        }
        if ($e instanceof UncompletedChecklistException) {
            return $e->render();
        }
        if ($e instanceof WorkflowException) {
            return $this->errorResponse($e->getMessage(), null, $e->getStatusCode());
        }
        if ($e instanceof ModelNotFoundException) {
            return $this->errorResponse('Recurso no encontrado', null, 404);
        }

        return $this->errorResponse($message, $e->getMessage(), 500);
    }
}
