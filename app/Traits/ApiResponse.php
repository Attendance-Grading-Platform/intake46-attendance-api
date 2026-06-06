<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a standardised success JSON response.
     *
     * Shape: { "status": "success", "message": "...", "data": { … } }
     *
     * @param  mixed        $data    Payload (model, collection, array, etc.)
     * @param  string|null  $message Optional human-readable message.
     * @param  int          $code    HTTP status code (default 200).
     */
    protected function successResponse(mixed $data, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Return a standardised error JSON response.
     *
     * Shape: { "status": "error", "message": "..." }
     *
     * @param  string  $message Human-readable error description.
     * @param  int     $code    HTTP status code (e.g. 401, 404, 422, 500).
     */
    protected function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
        ], $code);
    }
}
