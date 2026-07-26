<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Centralised exception -> envelope mapping.
 *
 * Registered from bootstrap/app.php. This is the ONE place that turns any
 * thrown exception on an API path into the service's stable error envelope,
 * so no controller ever formats an error by hand.
 */
final class Handler
{
    /**
     * Wire all API exception rendering. Called from bootstrap/app.php.
     */
    public static function register(Exceptions $exceptions): void
    {
        // A duplicate event is normal idempotent flow (returns 200), NOT a
        // failure — don't spam the error log with a stack trace on every retry.
        $exceptions->dontReport(DuplicateEventException::class);

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! self::wantsEnvelope($request)) {
                return null; // fall back to default rendering for non-API paths
            }

            return match (true) {
                // 200 — idempotent duplicate: tell the caller it's already done.
                $e instanceof DuplicateEventException => ApiResponse::success(
                    ['event_id' => $e->eventId, 'duplicate' => true],
                    'Event already processed',
                    200,
                ),

                // 422 — validation failure (FormRequest / Validator).
                $e instanceof ValidationException => ApiResponse::error(
                    'The given data was invalid.',
                    $e->errors(),
                    422,
                ),

                // 401 — bad client credentials or auth failure.
                $e instanceof InvalidCredentialsException => ApiResponse::error(
                    $e->getMessage(),
                    [],
                    401,
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    'Unauthenticated.',
                    [],
                    401,
                ),

                // 404 — unknown route / model.
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'Resource not found.',
                    [],
                    404,
                ),

                // Any other HTTP exception (429 throttle, 405, ...) keeps its code.
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'Request failed.',
                    [],
                    $e->getStatusCode(),
                ),

                // 500 — unexpected. Hide internals unless APP_DEBUG is on.
                default => ApiResponse::error(
                    config('app.debug') ? $e->getMessage() : 'Server error.',
                    [],
                    500,
                ),
            };
        });
    }

    private static function wantsEnvelope(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
