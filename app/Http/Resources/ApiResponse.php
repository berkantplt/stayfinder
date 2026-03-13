<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API Response Helper
 *
 * Provides a consistent JSON envelope for all API responses.
 * Every response follows the same structure:
 * {
 *   "success": bool,
 *   "data": mixed,
 *   "message": string,
 *   "errors": array
 * }
 */
class ApiResponse
{
    /**
     * Return a successful response.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ], $status);
    }

    /**
     * Return an error response.
     */
    public static function error(string $message = 'Error', int $status = 500, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Return a validation error response (422).
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Return a rate-limited response (429).
     */
    public static function tooManyRequests(string $message = 'Too many requests. Please try again later.'): JsonResponse
    {
        return self::error($message, 429);
    }
}
