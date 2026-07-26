<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\InvalidCredentialsException;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Support\Jwt\Contracts\JwtCodecInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and (implicitly) governs the service's machine-to-machine JWTs.
 *
 * Flow: client presents client_id + raw secret -> we verify the HASHED secret
 * -> we mint a short-lived signed JWT carrying the client's identity.
 */
final class AuthService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly JwtCodecInterface $jwt,
    ) {}

    /**
     * Verify a client_id / raw-secret pair against the stored hash.
     *
     * @throws InvalidCredentialsException on unknown client or bad secret.
     */
    public function authenticateClient(string $clientId, string $secret): Client
    {
        $client = $this->clients->findByClientId($clientId);

        // One branch for "not found" and "wrong secret" => no user enumeration.
        // Hash::check also provides constant-time comparison.
        if ($client === null || ! Hash::check($secret, $client->client_secret)) {
            throw new InvalidCredentialsException;
        }

        return $client;
    }

    /**
     * Mint a signed, short-lived JWT for an authenticated client.
     */
    public function issueToken(Client $client): string
    {
        $now = time();
        $ttl = (int) config('jwt.ttl');

        return $this->jwt->encode([
            'iss' => config('jwt.issuer'),   // who issued it
            'iat' => $now,                    // issued-at
            'nbf' => $now,                    // not-before
            'exp' => $now + $ttl,             // expiry
            'sub' => $client->client_id,      // subject = public client id
            'cid' => $client->id,             // internal client PK (fast resolve)
        ]);
    }
}
