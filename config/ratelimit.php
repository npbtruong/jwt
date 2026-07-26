<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Per-endpoint rate limits ("maxAttempts,perMinutes")
    |--------------------------------------------------------------------------
    | Consumed by routes/api.php as `throttle:<value>`. Config-driven so limits
    | change per environment without touching route code.
    */

    // Token minting is expensive/sensitive => tight limit.
    'token' => env('RATELIMIT_TOKEN', '10,1'),

    // Webhook ingestion is high-volume => looser limit.
    'webhook' => env('RATELIMIT_WEBHOOK', '60,1'),
];
