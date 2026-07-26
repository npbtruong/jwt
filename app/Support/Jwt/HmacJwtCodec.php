<?php

declare(strict_types=1);

namespace App\Support\Jwt;

use App\Exceptions\TokenExpiredException;
use App\Exceptions\TokenInvalidException;
use App\Support\Jwt\Contracts\JwtCodecInterface;

/**
 * Minimal, self-contained HS256 (HMAC-SHA256) JWT codec.
 *
 * A JWT is three base64url segments joined by dots:
 *
 *     base64url(header) . base64url(payload) . base64url(signature)
 *
 * The signature is HMAC-SHA256 over "header.payload" using a shared secret.
 * Verification recomputes that HMAC and compares it in constant time, then
 * enforces the exp/nbf time claims. Kept deliberately small so the mechanism
 * is fully visible for study.
 */
final class HmacJwtCodec implements JwtCodecInterface
{
    private const SUPPORTED_ALG = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly string $algo = 'HS256',
        private readonly int $leeway = 5,
    ) {}

    public function encode(array $claims): string
    {
        $this->guardAlg();

        $header = $this->base64UrlEncode($this->jsonEncode([
            'typ' => 'JWT',
            'alg' => $this->algo,
        ]));

        $payload = $this->base64UrlEncode($this->jsonEncode($claims));

        $signature = $this->sign("{$header}.{$payload}");

        return "{$header}.{$payload}.{$signature}";
    }

    public function decode(string $token): array
    {
        $this->guardAlg();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new TokenInvalidException('Token structure is invalid.');
        }

        [$header64, $payload64, $signature64] = $parts;

        // 1) Verify signature in constant time (hash_equals) before trusting anything.
        $expected = $this->sign("{$header64}.{$payload64}");
        if (! hash_equals($expected, $signature64)) {
            throw new TokenInvalidException('Signature verification failed.');
        }

        // 2) Confirm the header alg matches what we support (defends against alg swap).
        $header = $this->jsonDecode($this->base64UrlDecode($header64));
        if (($header['alg'] ?? null) !== $this->algo) {
            throw new TokenInvalidException('Unexpected token algorithm.');
        }

        // 3) Decode + validate time claims.
        $payload = $this->jsonDecode($this->base64UrlDecode($payload64));
        $now = time();

        if (isset($payload['nbf']) && $now + $this->leeway < (int) $payload['nbf']) {
            throw new TokenInvalidException('Token is not yet valid.');
        }

        if (isset($payload['exp']) && $now - $this->leeway >= (int) $payload['exp']) {
            throw new TokenExpiredException('Token has expired.');
        }

        return $payload;
    }

    private function sign(string $signingInput): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $signingInput, $this->secret, true)
        );
    }

    private function guardAlg(): void
    {
        if ($this->algo !== self::SUPPORTED_ALG) {
            throw new TokenInvalidException("Unsupported JWT algorithm [{$this->algo}].");
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new TokenInvalidException('Token contains invalid base64.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonDecode(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new TokenInvalidException('Token payload is not a JSON object.');
        }

        return $decoded;
    }
}
