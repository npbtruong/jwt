<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Reservation;
use App\Repositories\Contracts\ReservationRepositoryInterface;

final class ReservationRepository implements ReservationRepositoryInterface
{
    public function upsertByEventId(array $data): Reservation
    {
        // Keyed on event_id so a replay updates the existing row instead of
        // creating a duplicate. Complements the DB unique index on event_id.
        return Reservation::updateOrCreate(
            ['event_id' => $data['event_id']],
            $data,
        );
    }
}
