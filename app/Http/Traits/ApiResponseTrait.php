<?php

namespace App\Http\Traits;

trait ApiResponseTrait
{
    /**
     * Formatea una respuesta JSON de éxito.
     *
     * @param mixed  $data
     * @param string $message
     * @param int    $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data, string $message, int $code = 200)
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Formatea una respuesta JSON de error.
     *
     * @param string    $message
     * @param mixed|null $error
     * @param int       $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, $error = null, int $code = 400)
    {
        $response = [
            'status'  => false,
            'message' => $message,
        ];

        if (!is_null($error)) {
            $response['error'] = $error;
        }

        return response()->json($response, $code);
    }
}

