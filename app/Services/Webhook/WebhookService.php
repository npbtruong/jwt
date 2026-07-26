<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use App\Exceptions\DuplicateEventException;
use App\Jobs\SendReservationNotificationJob;
use App\Models\Client;
use App\Models\Reservation;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\Idempotency\Contracts\IdempotencyStoreInterface;
use Illuminate\Support\Facades\DB;

/**
 * The heart of the service. For each webhook it:
 *   1. atomically de-duplicates on event_id (Redis SET NX EX),
 *   2. persists the reservation SYNCHRONOUSLY inside a DB transaction,
 *   3. dispatches the (slow) email job AFTER COMMIT onto the Redis queue,
 *   4. hands a persisted Reservation back to the controller to respond.
 *
 * The email is deliberately async: the OTA gets its 200 the instant the row
 * is durable, and mail delivery happens off-request on the worker.
 */
final class WebhookService
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly IdempotencyStoreInterface $idempotency,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  validated webhook body
     *
     * @throws DuplicateEventException when event_id was already processed.
     */
    public function process(array $payload, Client $client): Reservation
    {
        $eventId = (string) $payload['event_id'];

        // (1) Atomic idempotency gate. acquire() = SET <key> 1 NX EX ttl.
        //     Returns false when the key already exists => duplicate retry.
        if (! $this->idempotency->acquire($this->key($eventId), $this->ttl())) {
            throw new DuplicateEventException($eventId);
        }

        // (2) + (3) Persist and queue in one transaction. afterCommit() guarantees
        //     the job is only enqueued once the row is durably committed — if the
        //     transaction rolls back, no email is ever sent.
        return DB::transaction(function () use ($payload, $eventId): Reservation {
            $reservation = $this->reservations->upsertByEventId(
                $this->toRow($eventId, $payload['reservation'])
            );

            SendReservationNotificationJob::dispatch($reservation)->afterCommit();

            return $reservation;
        });
    }

    /**
     * Peek whether an event_id has been seen. NOTE: uses the same atomic
     * acquire() and therefore RESERVES the key — process() is the real gate;
     * this exists for the interface/spec and isolated testing.
     */
    public function isDuplicate(string $eventId): bool
    {
        return ! $this->idempotency->acquire($this->key($eventId), $this->ttl());
    }

    /**
     * Explicitly reserve an event_id via the idempotency store.
     */
    public function markProcessed(string $eventId): void
    {
        $this->idempotency->acquire($this->key($eventId), $this->ttl());
    }

    /**
     * Map the nested "reservation" payload to a flat DB row, stamping event_id.
     *
     * @param  array<string, mixed>  $reservation
     * @return array<string, mixed>
     */
    private function toRow(string $eventId, array $reservation): array
    {
        return [
            'event_id' => $eventId,
            'ota_reservation_id' => $reservation['ota_reservation_id'],
            'property_id' => $reservation['property_id'],
            'guest_name' => $reservation['guest_name'],
            'guest_email' => $reservation['guest_email'],
            'check_in' => $reservation['check_in'],
            'check_out' => $reservation['check_out'],
            'room_type' => $reservation['room_type'],
            'total_amount' => $reservation['total_amount'],
            'currency' => $reservation['currency'],
            'status' => $reservation['status'],
        ];
    }

    private function key(string $eventId): string
    {
        return config('idempotency.prefix').$eventId;
    }

    private function ttl(): int
    {
        return (int) config('idempotency.ttl');
    }
}
