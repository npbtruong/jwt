<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | JWT signing secret
    |--------------------------------------------------------------------------
    | Symmetric (HS256) secret used to sign and verify service tokens.
    | Falls back to APP_KEY so the service boots out-of-the-box, but you
    | SHOULD set a dedicated JWT_SECRET in production.
    */
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),

    /*
    | Signing algorithm. HS256 is symmetric (one shared secret). Swap to an
    | RS256 keypair here if independent services must verify without the secret.
    */
    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    | Token time-to-live in seconds. Returned to the client as `expires_in`.
    */
    'ttl' => (int) env('JWT_TTL', 3600),

    /*
    | Issuer claim (iss). Lets consumers/verifiers pin the token origin.
    */
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'webhook-receiver')),

    /*
    | Optional leeway (seconds) to tolerate small clock skew between nodes.
    */
    'leeway' => (int) env('JWT_LEEWAY', 5),
];
