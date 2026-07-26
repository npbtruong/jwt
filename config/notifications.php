<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notification queue job settings
    |--------------------------------------------------------------------------
    | Drives SendReservationNotificationJob. All tunable per-environment so the
    | job code never hard-codes retry policy.
    */

    // Named Redis queue the job is pushed onto (worker listens on this).
    'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),

    // Max attempts before the job is marked failed.
    'tries' => (int) env('NOTIFICATIONS_TRIES', 3),

    // Per-attempt backoff in seconds (comma-separated => growing delay).
    'backoff' => array_map(
        'intval',
        explode(',', (string) env('NOTIFICATIONS_BACKOFF', '10,30,60'))
    ),

    // Hard timeout (seconds) for a single job run.
    'timeout' => (int) env('NOTIFICATIONS_TIMEOUT', 30),
];
