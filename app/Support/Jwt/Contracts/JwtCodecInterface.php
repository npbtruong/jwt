<?php

declare(strict_types=1);

namespace App\Support\Jwt\Contracts;

use App\Exceptions\TokenExpiredException;
use App\Exceptions\TokenInvalidException;

/**
 * Encode/decode signed JWTs. Callers depend on this interface, so the signing
 * strategy (HMAC now, RSA later) can change without touching auth code.
 */
interface JwtCodecInterface
{
    /**
     * Sign a claims set into a compact JWT string.
     *
     * @param  array<string, mixed>  $claims
     */
    public function encode(array $claims): string;

    /**
     * Verify signature + time claims and return the payload.
     *
     * @return array<string, mixed>
     *
     * @throws TokenExpiredException when the `exp` claim is in the past.
     * @throws TokenInvalidException on malformed token or bad signature.
     */
    public function decode(string $token): array;
}
