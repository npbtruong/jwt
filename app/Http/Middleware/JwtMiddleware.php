<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TokenExpiredException;
use App\Exceptions\TokenInvalidException;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Support\ApiResponse;
use App\Support\Jwt\Contracts\JwtCodecInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the Bearer JWT on protected routes.
 *
 *  - checks signature + expiry (stateless: no DB hit to validate the token),
 *  - resolves the owning Client and attaches it to the request,
 *  - any failure short-circuits with the standard 401 envelope.
 *
 * The resolved client is available downstream via:
 *     $request->attributes->get('auth_client')
 */
final class JwtMiddleware
{
    // Request attribute key under which the authenticated client is stored.
    public const CLIENT_ATTRIBUTE = 'auth_client';

    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly JwtCodecInterface $jwt,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return ApiResponse::error('Authorization token not provided.', [], 401);
        }

        try {
            $claims = $this->jwt->decode($token);
        } catch (TokenExpiredException) {
            return ApiResponse::error('Token has expired.', [], 401);
        } catch (TokenInvalidException) {
            return ApiResponse::error('Invalid token.', [], 401);
        }

        // Resolve the client the token was issued for (sub = client_id).
        $client = $this->clients->findByClientId((string) ($claims['sub'] ?? ''));

        if ($client === null) {
            return ApiResponse::error('Client no longer exists.', [], 401);
        }

        $request->attributes->set(self::CLIENT_ATTRIBUTE, $client);

        return $next($request);
    }
}
