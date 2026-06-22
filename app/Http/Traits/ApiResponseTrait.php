<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Formatea una respuesta JSON de éxito.
     *
     * @param  mixed  $data
     * @return JsonResponse
     */
    protected function successResponse($data, string $message, int $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Formatea una respuesta JSON de error.
     *
     * @param  mixed|null  $error
     * @return JsonResponse
     */
    protected function errorResponse(string $message, $error = null, int $code = 400)
    {
        $response = [
            'status' => false,
            'message' => $message,
        ];

        if (! is_null($error)) {
            $response['error'] = $error;
        }

        return response()->json($response, $code);
    }
}
