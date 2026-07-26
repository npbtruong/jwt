<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Reservation;

interface ReservationRepositoryInterface
{
    /**
     * Insert or update a reservation keyed by its unique event_id.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertByEventId(array $data): Reservation;
}
