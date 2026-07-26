<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Idempotency key prefix
    |--------------------------------------------------------------------------
    | Every event_id is namespaced under this prefix in Redis so idempotency
    | keys never collide with cache/session/queue keys.
    */
    'prefix' => env('IDEMPOTENCY_PREFIX', 'idem:webhook:'),

    /*
    | How long (seconds) a processed event_id is remembered. Must comfortably
    | exceed the OTA's maximum retry window. Default: 24h.
    */
    'ttl' => (int) env('IDEMPOTENCY_TTL', 86400),
];
