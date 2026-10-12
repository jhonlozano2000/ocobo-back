<?php

namespace App\Http\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

/**
 * Trait para estandarizar las respuestas JSON de la API en OCOBO.
 *
 * Cada método define el código de estado HTTP adecuado, el payload JSON estándar
 * ({status, success, message, data|errors}), y se sincroniza directamente con
 * el sistema de toasts del frontend:
 *
 *  HTTP Code | Significado REST              | Método ApiResponseTrait        | Toast Frontend
 * -----------|-------------------------------|--------------------------------|-----------------
 *  200 / 201 | Éxito / Recurso Creado        | successResponse, createdResponse| toastSuccess (Verde)
 *  204       | Sin Contenido                 | noContentResponse               | -
 *  400       | Solicitud Incorrecta          | badRequestResponse             | toastWarning (Amarillo)
 *  401       | No Autenticado                | unauthorizedResponse           | toastWarning (Amarillo)
 *  403       | No Autorizado / Prohibido     | forbiddenResponse              | toastError (Rojo)
 *  404       | Recurso No Encontrado         | notFoundResponse               | toastInfo (Azul)
 *  409       | Conflicto de Estado           | conflictResponse               | toastWarning (Amarillo)
 *  422       | Validación / Regla de Negocio | validationResponse, warningResponse | toastWarning (Amarillo)
 *  429       | Límite de Peticiones          | tooManyRequestsResponse        | toastWarning (Amarillo)
 *  500       | Error Interno del Servidor    | serverErrorResponse            | toastError (Rojo)
 */
trait ApiResponseTrait
{
    /**
     * Formatea una respuesta JSON de éxito (HTTP 200).
     * Frontend: Muestra toastSuccess (Verde).
     *
     * @param  mixed  $data  Datos a retornar en el payload
     * @param  string  $message  Mensaje informativo de la operación
     * @param  int  $code  Código de estado HTTP (por defecto 200)
     */
    protected function successResponse($data, string $message = 'Operación realizada exitosamente', int $code = 200): JsonResponse
    {
        $response = [
            'status' => true,
            'success' => true,
            'message' => $message,
        ];

        if ($data instanceof LengthAwarePaginator) {
            $response['data'] = $data->items();
            $response['pagination'] = [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
            ];
        } elseif ($data instanceof Paginator) {
            $response['data'] = $data->items();
            $response['pagination'] = [
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'has_more' => $data->hasMorePages(),
            ];
        } elseif ($data instanceof AnonymousResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $response['data'] = $data;
            $response['pagination'] = [
                'total' => $data->resource->total(),
                'per_page' => $data->resource->perPage(),
                'current_page' => $data->resource->currentPage(),
                'last_page' => $data->resource->lastPage(),
            ];
        } elseif ($data instanceof AnonymousResourceCollection && $data->resource instanceof Paginator) {
            $response['data'] = $data;
            $response['pagination'] = [
                'per_page' => $data->resource->perPage(),
                'current_page' => $data->resource->currentPage(),
                'has_more' => $data->resource->hasMorePages(),
            ];
        } else {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Formatea una respuesta JSON de recurso creado exitosamente (HTTP 201).
     * Frontend: Muestra toastSuccess (Verde).
     *
     * @param  mixed  $data  Entidad o recurso creado
     * @param  string  $message  Mensaje descriptivo
     */
    protected function createdResponse($data, string $message = 'Recurso creado exitosamente'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Formatea una respuesta sin contenido (HTTP 204).
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Formatea una respuesta JSON de error genérica.
     *
     * @param  string  $message  Mensaje de error
     * @param  mixed|null  $error  Detalle de errores técnicos o array de validación
     * @param  int  $code  Código HTTP de error (por defecto 400)
     */
    protected function errorResponse(string $message, $error = null, int $code = 400): JsonResponse
    {
        $response = [
            'status' => false,
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($error)) {
            // M08-16 (CWE-209): los controladores pasan $e->getMessage() en los 500; fuera de debug el
            // detalle (SQL, rutas, clases) va al log y el cliente solo recibe el mensaje genérico
            if ($code >= 500 && ! config('app.debug')) {
                Log::error($message, ['error' => $error, 'url' => request()->fullUrl()]);
            } else {
                $response['errors'] = $error;
            }
        }

        return response()->json($response, $code);
    }

    /**
     * Formatea una respuesta de error de validación o formulario (HTTP 422).
     * Extrae el primer mensaje de error para mostrar en el toast principal
     * y conserva el mapa de errores en 'errors' para marcar los campos del formulario.
     * Frontend: Muestra toastWarning (Amarillo).
     *
     * @param  mixed  $errors  Array de errores de validación (ej. $validator->errors())
     * @param  string  $message  Mensaje general de validación opcional
     */
    protected function validationResponse($errors, string $message = 'Error de validación'): JsonResponse
    {
        $firstError = is_array($errors)
            ? (collect($errors)->flatten()->first() ?? $message)
            : ($errors instanceof MessageBag
                ? ($errors->first() ?? $message)
                : (string) $errors);

        return response()->json([
            'status' => false,
            'success' => false,
            'message' => $firstError,
            'errors' => is_array($errors) ? $errors : ['error' => [$firstError]],
        ], 422);
    }

    /**
     * Formatea una advertencia o regla de negocio no cumplida (HTTP 422).
     * Se usa cuando la petición es técnicamente correcta pero viola una política del negocio
     * (ej. saldo insuficiente, radicado ya asignado, documento ya firmado, plazo vencido).
     * Frontend: Muestra toastWarning (Amarillo).
     *
     * @param  string  $message  Mensaje de advertencia de la regla de negocio
     * @param  mixed|null  $error  Detalles adicionales opcionales
     */
    protected function warningResponse(string $message, $error = null): JsonResponse
    {
        return $this->errorResponse($message, $error, 422);
    }

    /**
     * Formatea una respuesta de recurso no encontrado (HTTP 404).
     * Frontend: Muestra toastInfo (Azul).
     *
     * @param  string  $message  Mensaje indicando el recurso faltante
     */
    protected function notFoundResponse(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return $this->errorResponse($message, null, 404);
    }

    /**
     * Formatea una respuesta de solicitud incorrecta o malformada (HTTP 400).
     * Frontend: Muestra toastWarning (Amarillo).
     *
     * @param  string  $message  Mensaje descriptivo del error de la solicitud
     * @param  mixed|null  $error  Detalle técnico
     */
    protected function badRequestResponse(string $message = 'Solicitud incorrecta', $error = null): JsonResponse
    {
        return $this->errorResponse($message, $error, 400);
    }

    /**
     * Formatea una respuesta de falta de autenticación (HTTP 401).
     * Frontend: Muestra toastWarning (Amarillo) e inicia redirección al login.
     *
     * @param  string  $message  Mensaje de autenticación requerida
     */
    protected function unauthorizedResponse(string $message = 'No autenticado o sesión expirada'): JsonResponse
    {
        return $this->errorResponse($message, null, 401);
    }

    /**
     * Formatea una respuesta de acceso prohibido o falta de permisos (HTTP 403).
     * Frontend: Muestra toastError (Rojo).
     *
     * @param  string  $message  Mensaje de permisos insuficientes
     */
    protected function forbiddenResponse(string $message = 'No tiene permisos para realizar esta acción'): JsonResponse
    {
        return $this->errorResponse($message, null, 403);
    }

    /**
     * Formatea una respuesta de conflicto de concurrencia o estado (HTTP 409).
     * Se usa cuando el recurso está bloqueado por otro usuario o fue modificado concurrentemente.
     * Frontend: Muestra toastWarning (Amarillo).
     *
     * @param  string  $message  Descripción del conflicto
     * @param  mixed|null  $error  Datos del conflicto (ej. usuario que tiene el bloqueo)
     */
    protected function conflictResponse(string $message = 'Conflicto con el estado actual del recurso', $error = null): JsonResponse
    {
        return $this->errorResponse($message, $error, 409);
    }

    /**
     * Formatea una respuesta por exceso de peticiones / rate limit (HTTP 429).
     * Frontend: Muestra toastWarning (Amarillo).
     *
     * @param  string  $message  Mensaje de límite de peticiones superado
     */
    protected function tooManyRequestsResponse(string $message = 'Demasiadas solicitudes. Por favor, espere un momento antes de reintentar.'): JsonResponse
    {
        return $this->errorResponse($message, null, 429);
    }

    /**
     * Formatea una respuesta de error interno no controlado del servidor (HTTP 500).
     * Frontend: Muestra toastError (Rojo).
     *
     * @param  string  $message  Mensaje del error para el usuario
     * @param  mixed|null  $error  Mensaje técnico o traza (en debug)
     */
    protected function serverErrorResponse(string $message = 'Ha ocurrido un error interno en el servidor', $error = null): JsonResponse
    {
        return $this->errorResponse($message, $error, 500);
    }
}
