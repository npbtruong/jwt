<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Single source of truth for the service's response envelope.
 *
 * Every endpoint and every exception path funnels through here so consumers
 * (ms-report / extranet) can depend on one stable shape:
 *
 *   success => { "success": true,  "message": "...", "data":   { ... } }
 *   error   => { "success": false, "message": "...", "errors": { ... } }
 */
final class ApiResponse
{
    /**
     * Build a success envelope.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Build an error envelope.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            // Cast to object so an empty bag serialises as {} (not []).
            'errors' => (object) $errors,
        ], $status);
    }
}
