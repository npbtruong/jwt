<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an event_id has already been processed (idempotency hit).
 *
 * This is NOT a failure — it signals a duplicate OTA retry. The handler maps
 * it to a 200 "already processed" envelope so the caller stops retrying.
 */
final class DuplicateEventException extends RuntimeException
{
    public function __construct(public readonly string $eventId)
    {
        parent::__construct("Event {$eventId} already processed");
    }
}
